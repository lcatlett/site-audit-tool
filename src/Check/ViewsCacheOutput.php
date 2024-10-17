<?php

/**
 * @file
 * Contains Drupal\site_audit\Plugin\SiteAuditCheck\ViewsCacheOutput
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;
use SiteAudit\Util\SiteAuditEnvironment;

/**
 * Provides the ViewsCacheOutput Check.
 */
class ViewsCacheOutput extends SiteAuditCheckBase
{

  /**
   * {@inheritdoc}.
   */
  public function getId()
  {
    return 'views_cache_output';
  }

  /**
   * {@inheritdoc}.
   */
  public function getLabel()
  {
    return $this->t('Rendered output caching');
  }

  /**
   * {@inheritdoc}.
   */
  public function getDescription()
  {
    return $this->t("Check if views have rendered output caching enabled.");
  }

  /**
   * {@inheritdoc}.
   */
  public function getReportId()
  {
    return 'views';
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultFail() {}

  /**
   * {@inheritdoc}.
   */
  public function getResultInfo()
  {
    return $this->simpleKeyValueList($this->t('View'), $this->t('Display'), $this->registry->views_cache_output);
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
  public function calculateScore()
  {
    $this->registry->views_cache_output = array();

    if ($this->isDrupal7()) {
      $views = views_get_all_views();
    } else {
      $views = \Drupal\views\Views::getAllViews();
    }

    foreach ($views as $view) {
      $displays = $this->isDrupal7() ? $view->display : $view->get('display');
      foreach ($displays as $display_id => $display) {
        $display = (array) $display;
        if (isset($display['display_options']['cache']['type']) && $display['display_options']['cache']['type'] != 'none') {
          $view_name = $this->isDrupal7() ? $view->name : $view->id();
          $this->registry->views_cache_output[$view_name][$display_id] = $display['display_options']['cache']['type'];
        }
      }
    }

    if (empty($this->registry->views_cache_output)) {
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_FAIL;
    }

    return SiteAuditCheckBase::AUDIT_CHECK_SCORE_INFO;
  }

  protected function isDrupal7()
  {
    return version_compare(VERSION, '8.0', '<');
  }
}
