<?php
/**
 * @file
 * Contains Drupal\site_audit\Plugin\SiteAuditCheck\UsersCountBlocked
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;
use Drupal\Core\Database\Database;

/**
 * Provides the UsersCountBlocked Check.
 */
class UsersCountBlocked extends SiteAuditCheckBase {

  /**
   * {@inheritdoc}.
   */
  public function getId() {
    return 'users_count_blocked';
  }

  /**
   * {@inheritdoc}.
   */
  public function getLabel() {
    return $this->t('Count Blocked');
  }

  /**
   * {@inheritdoc}.
   */
  public function getDescription() {
    return $this->t("Total number of blocked Drupal users.");
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
  public function getResultFail() {}

  /**
   * {@inheritdoc}.
   */
  public function getResultInfo() {
    switch ($this->registry->count_users_blocked) {
      case 0:
        return $this->t('There are no blocked users.');
      case 1:
        return $this->t('There is one blocked user.');
      default:
        return $this->t('There are @count blocked users.', ['@count' => $this->registry->count_users_blocked]);
    }
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
      $query = db_select('users', 'u')
        ->condition('uid', 0, '>')
        ->condition('status', 0);
      $query->addExpression('COUNT(*)', 'count');
      $this->registry->count_users_blocked = $query->execute()->fetchField();
    } else {
      $query = \Drupal::database()->select('users_field_data', 'ufd');
      $query->addExpression('COUNT(*)', 'count');
      $query->condition('uid', 0, '>');
      $query->condition('status', 0);
      $this->registry->count_users_blocked = $query->execute()->fetchField();
    }
    return SiteAuditCheckBase::AUDIT_CHECK_SCORE_INFO;
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
