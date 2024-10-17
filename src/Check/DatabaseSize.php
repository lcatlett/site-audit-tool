<?php

/**
 * @file
 * Contains Drupal\site_audit\Plugin\SiteAuditCheck\DatabaseSize
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;

/**
 * Provides the Database size check.
 */
class DatabaseSize extends SiteAuditCheckBase
{

  /**
   * Total database size.
   *
   * @var float
   */
  protected $totalSize;

  /**
   * Cache tables size.
   *
   * @var float
   */
  protected $cacheSize;

  /**
   * {@inheritdoc}.
   */
  public function getId()
  {
    return 'database_size';
  }

  /**
   * {@inheritdoc}.
   */
  public function getLabel()
  {
    return $this->t('Database size');
  }

  /**
   * {@inheritdoc}.
   */
  public function getDescription()
  {
    return $this->t("Determine the size of the database and cache tables.");
  }

  /**
   * {@inheritdoc}.
   */
  public function getReportId()
  {
    return 'database';
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultFail()
  {
    return $this->t('Empty, or unable to determine the size due to a permission error.');
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultInfo()
  {
    $totalSizeMB = number_format($this->totalSize / 1048576, 2);
    $cacheSizeMB = number_format($this->cacheSize / 1048576, 2);
    $cachePercentage = number_format(($this->cacheSize / $this->totalSize) * 100, 2);

    return $this->t('Total database size: @total_size MB<br>Cache tables size: @cache_size MB (@cache_percentage% of total)', [
      '@total_size' => $totalSizeMB,
      '@cache_size' => $cacheSizeMB,
      '@cache_percentage' => $cachePercentage,
    ]);
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultPass() {}

  /**
   * {@inheritdoc}.
   */
  public function getResultWarn()
  {
    return $this->t('Cache tables occupy more than 20% of the total database size. Consider clearing caches or optimizing cache configuration.');
  }

  /**
   * {@inheritdoc}.
   */
  public function getAction()
  {
    if (($this->cacheSize / $this->totalSize) > 0.2) {
      return $this->t('Review your cache configuration and consider clearing caches if necessary.');
    }
    return '';
  }

  /**
   * {@inheritdoc}.
   */
  public function calculateScore()
  {
    try {
      $connection = $this->getDatabaseConnection();
      $this->totalSize = $this->getDatabaseSize($connection);
      $this->cacheSize = $this->getCacheTablesSize($connection);

      if (!$this->totalSize) {
        $this->abort = TRUE;
        return SiteAuditCheckBase::AUDIT_CHECK_SCORE_FAIL;
      }

      if (($this->cacheSize / $this->totalSize) > 0.2) {
        return SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN;
      }

      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_INFO;
    } catch (Exception $e) {
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_FAIL;
    }
  }

  /**
   * Get the database connection.
   *
   * @return \Drupal\Core\Database\Connection|\DatabaseConnection
   *   The database connection.
   */
  protected function getDatabaseConnection()
  {
    if ($this->isDrupal7()) {
      return db_select('default');
    } else {
      return \Drupal::database();
    }
  }

  /**
   * Get the total database size.
   *
   * @param \Drupal\Core\Database\Connection|\DatabaseConnection $connection
   *   The database connection.
   *
   * @return float
   *   The total size of the database in bytes.
   */
  protected function getDatabaseSize($connection)
  {
    if ($this->isDrupal7()) {
      $query = db_query("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = :database", [
        ':database' => $connection->getConnectionOptions()['database'],
      ]);
      return (float) $query->fetchField();
    } else {
      $query = $connection->select('information_schema.TABLES', 'ist');
      $query->addExpression('SUM(ist.data_length + ist.index_length)');
      $query->condition('ist.table_schema', $connection->getConnectionOptions()['database']);
      return (float) $query->execute()->fetchField();
    }
  }

  /**
   * Get the total size of cache tables.
   *
   * @param \Drupal\Core\Database\Connection|\DatabaseConnection $connection
   *   The database connection.
   *
   * @return float
   *   The total size of cache tables in bytes.
   */
  protected function getCacheTablesSize($connection)
  {
    if ($this->isDrupal7()) {
      $query = db_query("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = :database AND table_name LIKE 'cache%'", [
        ':database' => $connection->getConnectionOptions()['database'],
      ]);
      return (float) $query->fetchField();
    } else {
      $query = $connection->select('information_schema.TABLES', 'ist');
      $query->addExpression('SUM(ist.data_length + ist.index_length)');
      $query->condition('ist.table_schema', $connection->getConnectionOptions()['database']);
      $query->condition('ist.table_name', 'cache%', 'LIKE');
      return (float) $query->execute()->fetchField();
    }
  }
}
