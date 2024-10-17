<?php
/**
 * @file
 * Contains Drupal\site_audit\Plugin\SiteAuditCheck\WatchdogEnabled
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;

/**
 * Provides the WatchdogEnabled Check.
 */
class WatchdogEnabled extends SiteAuditCheckBase {

  /**
   * {@inheritdoc}.
   */
  public function getId() {
    return 'watchdog_enabled';
  }

  /**
   * {@inheritdoc}.
   */
  public function getLabel() {
    return $this->t('dblog status');
  }

  /**
   * {@inheritdoc}.
   */
  public function getDescription() {
    return $this->t("Check to see if database logging is enabled.");
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
    return $this->t('Database logging (dblog) is not enabled; if the site is having problems, consider enabling it for debugging.');
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultPass() {
    return $this->t('Database logging (dblog) is enabled.');
  }

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
      $this->registry->watchdog_enabled = module_exists('dblog');
    } else {
      $this->registry->watchdog_enabled = \Drupal::moduleHandler()->moduleExists('dblog');
    }

    if (!$this->registry->watchdog_enabled) {
      $this->abort = TRUE;
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_INFO;
    }
    return SiteAuditCheckBase::AUDIT_CHECK_SCORE_PASS;
  }

}
