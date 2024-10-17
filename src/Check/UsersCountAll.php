<?php
/**
 * @file
 * Contains Drupal\site_audit\Plugin\SiteAuditCheck\UsersCountAll
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;

/**
 * Provides the UsersCountAll Check.
 */
class UsersCountAll extends SiteAuditCheckBase {

  /**
   * {@inheritdoc}.
   */
  public function getId() {
    return 'users_count_all';
  }

  /**
   * {@inheritdoc}.
   */
  public function getLabel() {
    return $this->t('Count All');
  }

  /**
   * {@inheritdoc}.
   */
  public function getDescription() {
    return $this->t("Total number of Drupal users.");
  }

  /**
   * {@inheritdoc}.
   */
  public function getReportId() {
    return 'users';
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultFail() {
    return $this->t('There are no users!');
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultInfo() {
    return $this->registry->count_users_all == 1
      ? $this->t('There is one user.')
      : $this->t('There are @count users.', ['@count' => $this->registry->count_users_all]);
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
    try {
      if ($this->isDrupal7()) {
        $count = db_query("SELECT COUNT(*) FROM {users} WHERE uid <> 0")->fetchField();
      } else {
        $count = \Drupal::database()->query("SELECT COUNT(*) FROM {users_field_data} WHERE uid <> 0")->fetchField();
      }

      $this->registry->count_users_all = $count;

      if (!$this->registry->count_users_all) {
        $this->abort = TRUE;
        return SiteAuditCheckBase::AUDIT_CHECK_SCORE_FAIL;
      }
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_INFO;
    }
    catch (Exception $e) {
      $this->abort = TRUE;
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_FAIL;
    }
  }

  /**
   * Check if the current Drupal version is 7.
   *
   * @return bool
   *   TRUE if Drupal 7, FALSE otherwise.
   */
  protected function isDrupal7() {
    return version_compare(VERSION, '8.0', '<');
  }
}
