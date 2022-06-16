<?php
/**
 * @file
 * Contains Drupal\site_audit\Plugin\SiteAuditCheck\BestPracticesFolderStructure
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;

/**
 * Provides the BestPracticesFolderStructure Check.
 */
class BestPracticesFolderStructure extends SiteAuditCheckBase {

  /**
   * @var string
   */
  private $infoMessage;

  /**
   * @var string
   */
  private $passMessage;

  /**
   * @var string
   */
  private $warningMessage;

  /**
   * @var string
   */
  private $actionMessage;

  /**
   * {@inheritdoc}.
   */
  public function getId() {
    return 'best_practices_folder_structure';
  }

  /**
   * {@inheritdoc}.
   */
  public function getLabel() {
    return $this->t('Folder Structure');
  }

  /**
   * {@inheritdoc}.
   */
  public function getDescription() {
    return $this->t("Checks if modules/contrib and modules/custom directory is present.");
  }

  /**
   * {@inheritdoc}.
   */
  public function getReportId() {
    return 'best_practices';
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultFail() {}

  /**
   * {@inheritdoc}.
   */
  public function getResultInfo() {}

  /**
   * {@inheritdoc}.
   */
  public function getResultPass() {
    return $this->t('modules/contrib and modules/custom directories exist.');
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultWarn() {
    if (!$this->registry->contrib && !$this->registry->custom) {
      return $this->t('Neither modules/contrib nor modules/custom directories are present!');
    }
    if (!$this->registry->contrib) {
      return $this->t('modules/contrib directory is not present!');
    }
    if (!$this->registry->custom) {
      return $this->t('modules/custom directory is not present!');
    }
  }

  /**
   * {@inheritdoc}.
   */
  public function getAction() {
    $message = '';
    if ($this->score == SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN) {
      if (!$this->registry->contrib && !$this->registry->custom) {
        $message .= $this->t('Put all the contrib modules inside the ./modules/contrib directory and custom modules inside the ./modules/custom directory.');
      }
      elseif (!$this->registry->contrib) {
        $message .= $this->t('Put all the contrib modules inside the ./modules/contrib directory.');
      }
      elseif (!$this->registry->custom) {
        $message .= $this->t('Put all the custom modules inside the ./modules/custom directory.');
      }
      return $message . ' ' . $this->t('Moving modules may cause errors, so refer to https://www.drupal.org/node/183681 for information on how to best proceed.');
    }
  }

  /**
   * {@inheritdoc}.
   */
  public function calculateScore() {
    $this->registry->contrib = is_dir(DRUPAL_ROOT . '/modules/contrib');
    $this->registry->custom = is_dir(DRUPAL_ROOT . '/modules/custom');
    if (!$this->registry->contrib || !$this->registry->custom) {
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN;
    }
    return SiteAuditCheckBase::AUDIT_CHECK_SCORE_PASS;
  }

}
