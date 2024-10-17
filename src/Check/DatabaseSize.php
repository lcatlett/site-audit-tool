<?php
/**
 * @file
 * Contains Drupal\site_audit\Plugin\SiteAuditCheck\DatabaseSize
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;
use Drupal\Core\Database\Database;

/**
 * Provides the Database size check.
 */
class DatabaseSize extends SiteAuditCheckBase {

  /**
   * {@inheritdoc}.
   */
  public function getId() {
    return 'database_size';
  }

  /**
   * {@inheritdoc}.
   */
  public function getLabel() {
    return $this->t('Database size');
  }

  /**
   * {@inheritdoc}.
   */
  public function getDescription() {
    return $this->t("Determine the size of the database, including cache tables.");
  }

  /**
   * {@inheritdoc}.
   */
  public function getReportId() {
    return 'database';
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultFail() {
    return $this->t('Unable to determine the database size.');
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultInfo() {
    return $this->t('Total database size: @total_size<br>Non-cache tables size: @non_cache_size<br>Cache tables size: @cache_size (@cache_percentage% of total)<br>Number of cache tables: @cache_count', [
      '@total_size' => $this->formatSize($this->registry->total_size),
      '@non_cache_size' => $this->formatSize($this->registry->non_cache_size),
      '@cache_size' => $this->formatSize($this->registry->cache_size),
      '@cache_percentage' => number_format($this->registry->cache_percentage, 2),
      '@cache_count' => $this->registry->cache_count,
    ]);
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultPass() {
    return $this->getResultInfo();
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultWarn() {
    return $this->t('Cache tables occupy @cache_percentage% of the total database size, which is higher than recommended.', [
      '@cache_percentage' => number_format($this->registry->cache_percentage, 2),
    ]) . '<br>' . $this->getResultInfo();
  }

  /**
   * {@inheritdoc}.
   */
  public function getAction() {
    if ($this->score == SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN) {
      return $this->t('Consider clearing caches or optimizing cache configuration to reduce database size.');
    }
    return NULL;
  }

  /**
   * {@inheritdoc}.
   */
  public function calculateScore() {
    $connection = Database::getConnection();
    try {
      // Get total database size
      $query = $connection->select('information_schema.TABLES', 'ist');
      $query->addExpression('SUM(ist.data_length + ist.index_length)');
      $query->condition('ist.table_schema', $connection->getConnectionOptions()['database']);
      $this->registry->total_size = $query->execute()->fetchField();

      // Get cache tables size and count
      $query = $connection->select('information_schema.TABLES', 'ist');
      $query->addExpression('SUM(ist.data_length + ist.index_length)');
      $query->addExpression('COUNT(ist.table_name)');
      $query->condition('ist.table_schema', $connection->getConnectionOptions()['database']);
      $query->condition('ist.table_name', 'cache%', 'LIKE');
      $result = $query->execute()->fetchAssoc();

      $this->registry->cache_size = $result['SUM(ist.data_length + ist.index_length)'];
      $this->registry->cache_count = $result['COUNT(ist.table_name)'];
      $this->registry->cache_percentage = ($this->registry->cache_size / $this->registry->total_size) * 100;

      if ($this->registry->cache_percentage > 20) {
        return SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN;
      }
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_PASS;
    }
    catch (\Exception $e) {
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_FAIL;
    }
  }
}
