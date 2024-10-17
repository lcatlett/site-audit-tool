<?php
/**
 * @file
 * Contains Drupal\site_audit\Plugin\SiteAuditCheck\ExtensionsCount
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;

/**
 * Provides the ExtensionsCount Check.
 */
class ExtensionsCount extends SiteAuditCheckBase {

  /**
   * {@inheritdoc}.
   */
  public function getId() {
    return 'extensions_count';
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
    return $this->t("Count the number of enabled extensions (modules and themes) in a site.");
  }

  /**
   * {@inheritdoc}.
   */
  public function getReportId() {
    return 'extensions';
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
    return $this->t('There are @extension_count extensions enabled.', array(
      '@extension_count' => $this->registry->extension_count,
    ));
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultWarn() {
    return $this->t('There are @extension_count extensions enabled; that\'s higher than the average.', array(
      '@extension_count' => $this->registry->extension_count,
    ));
  }

  /**
   * {@inheritdoc}.
   */
  public function getAction() {
    if ($this->score != SiteAuditCheckBase::AUDIT_CHECK_SCORE_PASS) {
      $ret_val = $this->t('Consider the following options:') . PHP_EOL;
      $options = array();
      $options[] = $this->t('Disable unneeded or unnecessary extensions.');
      $options[] = $this->t('Consolidate functionality if possible, or custom develop a solution specific to your needs.');
      $options[] = $this->t('Avoid using modules that serve only one small purpose that is not mission critical.');

      $ret_val .= $this->simpleList($options);

      $ret_val .= $this->t('A lightweight site is a fast and happy site!');
      return $ret_val;
    }
  }

  /**
   * {@inheritdoc}.
   */
  public function calculateScore() {
    if (!isset($this->registry->extensions) || empty($this->registry->extensions)) {
      if ($this->isDrupal7()) {
        $this->registry->extensions = module_list();
      } else {
        $moduleHandler = \Drupal::service('module_handler');
        $this->registry->extensions = $moduleHandler->getModuleList();
      }
    }

    $this->registry->extension_count = count($this->registry->extensions);

    if ($this->registry->extension_count >= 150) {
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN;
    }
    return SiteAuditCheckBase::AUDIT_CHECK_SCORE_PASS;
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
