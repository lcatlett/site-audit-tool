<?php
/**
 * @file
 * Contains Drupal\site_audit\Plugin\SiteAuditCheck\CacheBinsAll
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;

/**
 * Provides the CacheBinsAll Check.
 */
class CacheBinsAll extends SiteAuditCheckBase {

  /**
   * {@inheritdoc}.
   */
  public function getId() {
    return 'cache_bins_all';
  }

  /**
   * {@inheritdoc}.
   */
  public function getLabel() {
    return $this->t('Available cache bins');
  }

  /**
   * {@inheritdoc}.
   */
  public function getDescription() {
    return $this->t("All available cache bins.");
  }

  /**
   * {@inheritdoc}.
   */
  public function getReportId() {
    return 'cache';
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultFail() {}

  /**
   * {@inheritdoc}.
   */
  public function getResultInfo() {
    return $this->simpleKeyValueList($this->t('Bin'), $this->t('Class'), $this->registry->cache_bins_all);
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
      $this->registry->cache_bins_all = array();
      foreach (cache_get_bins() as $bin => $cache) {
        $this->registry->cache_bins_all[$bin] = get_class($cache);
      }
    } else {
      $container = \Drupal::getContainer();
      $services = $container->getServiceIds();

      $this->registry->cache_bins_all = array();
      $back_ends = preg_grep('/^cache\.backend\./', array_values($services));
      foreach ($back_ends as $backend) {
        $this->registry->cache_bins_all[$backend] = get_class($container->get($backend));
      }
    }

    return SiteAuditCheckBase::AUDIT_CHECK_SCORE_INFO;
  }

}
