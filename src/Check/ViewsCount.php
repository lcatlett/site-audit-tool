<?php
/**
 * @file
 * Contains Drupal\site_audit\Plugin\SiteAuditCheck\ViewsCount
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;
use Drupal\views\Views;

/**
 * Provides the ViewsCount Check.
 */
class ViewsCount extends SiteAuditCheckBase {

  /**
   * {@inheritdoc}.
   */
  public function getId() {
    return 'views_count';
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
    return $this->t("Number of enabled Views.");
  }

  /**
   * {@inheritdoc}.
   */
  public function getReportId() {
    return 'views';
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
    $views_count = count($this->registry->views);
    if (!$views_count) {
      return $this->t('There are no enabled views.');
    }
    return $this->t('There are @count_views enabled views.', array(
      '@count_views' => $views_count,
    ));
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultWarn() {
    return $this->getResultPass();
  }

  /**
   * {@inheritdoc}.
   */
  public function getAction() {
    if ($this->score == SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN) {
      return $this->t('Consider disabling the views module if you don\'t need it.');
    }
  }

  /**
   * {@inheritdoc}.
   */
  public function calculateScore() {
    $this->registry->views = [];

    if ($this->isDrupal7()) {
      if (module_exists('views')) {
        $views = views_get_all_views();
        foreach ($views as $view) {
          if (!empty($view->disabled)) {
            continue;
          }
          $this->registry->views[] = $view;
        }
      }
    } else {
      if (\Drupal::moduleHandler()->moduleExists('views')) {
        $this->registry->views = \Drupal::entityTypeManager()
          ->getStorage('view')
          ->loadMultiple();
      }
    }

    if (empty($this->registry->views)) {
      $this->abort = TRUE;
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
