<?php
/**
 * @file
 * Contains Drupal\site_audit\Plugin\SiteAuditCheck\UsersCountAll
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;
use Drupal\Core\Database\Database;

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
    return $this->formatPlural($this->registry->count_users_all, 'There is one user.', 'There are @count users.');
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
      $query = db_select('users');
      $query->addExpression('COUNT(*)', 'count');
      $query->condition('uid', 0, '<>');
    } else {
      $query = Database::getConnection()->select('users');
      $query->addExpression('COUNT(*)', 'count');
      $query->condition('uid', 0, '<>');
    }

    try {
      $this->registry->count_users_all = $query->execute()->fetchField();
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

}
