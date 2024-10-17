<?php
/**
 * @file
 * Contains Drupal\site_audit\Plugin\SiteAuditCheck\WatchdogCount
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;
use Drupal\Core\Database\Database;

/**
 * Provides the WatchdogCount Check.
 */
class WatchdogCount extends SiteAuditCheckBase {

  /**
   * {@inheritdoc}.
   */
  public function getId() {
    return 'watchdog_count';
  }

  /**
   * {@inheritdoc}.
   */
  public function getLabel() {
    return $this->t('Count');
  }

  /**
   * {@inheritdoc}.
   */
  public function getDescription() {
    return $this->t("Number of dblog entries.");
  }

  /**
   * {@inheritdoc}.
   */
  public function getReportId() {
    return 'watchdog';
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultFail() {}

  /**
   * {@inheritdoc}.
   */
  public function getResultInfo() {
    if (!$this->registry->count_entries) {
      return $this->t('There are no dblog entries.');
    }
    return $this->t('There are @count_entries log entries.', array(
      '@count_entries' => number_format($this->registry->count_entries),
    ));
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultPass() {}

  /**
   * {@inheritdoc}.
   */
  public function getResultWarn() {}

  /**
   * {@inheritdoc}.
   */
  public function getAction() {}

  /**
   * {@inheritdoc}.
   */
  public function calculateScore() {
    if ($this->isDrupal7()) {
      $query = db_select('watchdog');
    } else {
      $query = Database::getConnection()->select('watchdog');
    }
    $query->addExpression('COUNT(wid)', 'count');

    $this->registry->count_entries = $query->execute()->fetchField();

    if (!$this->registry->count_entries) {
      $this->abort = TRUE;
    }
    return SiteAuditCheckBase::AUDIT_CHECK_SCORE_INFO;
  }

}
