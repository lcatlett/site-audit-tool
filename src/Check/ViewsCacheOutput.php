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
class ViewsCacheOutput extends SiteAuditCheckBase {

  /**
   * {@inheritdoc}.
   */
  public function getId() {
    return 'views_cache_output';
  }

  /**
   * {@inheritdoc}.
   */
  public function getLabel() {
    return $this->t('Rendered output caching');
  }

  /**
   * {@inheritdoc}.
   */
  public function getDescription() {
    return $this->t("Check to see if raw rendered output is being cached.");
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
  public function getResultFail() {
    return $this->t('No View is caching rendered output!');
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultInfo() {
    return $this->getResultWarn();
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultPass() {
    return $this->t('Every View is caching rendered output.');
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultWarn() {
    return $this->t('The following Views are not caching rendered output: @views_without_output_caching', array(
      '@views_without_output_caching' => implode(', ', $this->registry->views_without_output_caching),
    ));
  }

  /**
   * {@inheritdoc}.
   */
  public function getAction() {
    if (!in_array($this->score, array(SiteAuditCheckBase::AUDIT_CHECK_SCORE_INFO, SiteAuditCheckBase::AUDIT_CHECK_SCORE_PASS))) {

      $steps = array(
        $this->t('Go to /admin/structure/views/'),
        $this->t('Edit the View in question'),
        $this->t('Select the Display'),
        $this->t('Click Advanced'),
        $this->t('Next to Caching, click to edit.'),
        $this->t('Caching: (something other than None)'),
      );

      $ret_val = $this->t('Rendered output should be cached for as long as possible (if the query changes, the output will be refreshed).');
      $ret_val .= $this->linebreak();
      $ret_val .= $this->simpleList($steps, 'ol');

      return $ret_val;
    }
  }

  /**
   * {@inheritdoc}.
   */
  public function calculateScore() {
    $this->registry->output_lifespan = array();
    if (empty($this->registry->views)) {
      $this->checkInvokeCalculateScore('views_count');
    }
    
    if ($this->isDrupal7()) {
      $views = views_get_all_views();
    } else {
      $views = \Drupal::entityTypeManager()->getStorage('view')->loadMultiple();
    }

    foreach ($views as $view) {
      $view_id = $this->isDrupal7() ? $view->name : $view->id();
      $tag = $this->isDrupal7() ? $view->tag : $view->get('tag');
      if (in_array($tag, array('admin', 'commerce'))) {
        continue;
      }
      
      $displays = $this->isDrupal7() ? $view->display : $view->get('display');
      foreach ($displays as $display_name => $display) {
        if (!isset($display['display_options']['enabled']) || $display['display_options']['enabled']) {
          // Default display OR overriding display.
          if (isset($display['display_options']['cache'])) {
            $cache_type = $display['display_options']['cache']['type'];
            if ($cache_type == 'none' || $cache_type == '') {
              $this->setOutputLifespan($view_id, $display_name, 'none');
            }
            elseif ($cache_type == 'time') {
              $lifespan = $this->getTimeLifespan($display['display_options']['cache']['options']);
              $this->setOutputLifespan($view_id, $display_name, $lifespan);
            }
            elseif ($cache_type == 'tag') {
              $this->setOutputLifespan($view_id, $display_name, 'tag');
            }
          }
          // Display is using default display's caching.
          else {
            $this->setOutputLifespan($view_id, $display_name, 'default');
          }
        }
      }
    }

    $this->registry->views_without_output_caching = array();

    foreach ($this->registry->output_lifespan as $view_name => $view_data) {
      $this->processViewCaching($view_name, $view_data);
    }

    if (count($this->registry->views_without_output_caching) == 0) {
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_PASS;
    }
    if (SiteAuditEnvironment::isDev()) {
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_INFO;
    }
    if (count($this->registry->views_without_output_caching) == count($this->registry->views)) {
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_FAIL;
    }
    return SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN;
  }

  /**
   * Set output lifespan for a view display.
   */
  private function setOutputLifespan($view_id, $display_name, $lifespan) {
    if ($display_name == 'default') {
      $this->registry->output_lifespan[$view_id]['default'] = $lifespan;
    } else {
      $this->registry->output_lifespan[$view_id]['displays'][$display_name] = $lifespan;
    }
  }

  /**
   * Get time lifespan from cache options.
   */
  private function getTimeLifespan($options) {
    if (!isset($options['output_lifespan'])) {
      return 'none';
    }
    if ($options['output_lifespan'] == 0) {
      $lifespan = isset($options['output_lifespan_custom']) ? $options['output_lifespan_custom'] : 0;
    } else {
      $lifespan = $options['output_lifespan'];
    }
    return $lifespan < 1 ? 'none' : $lifespan;
  }

  /**
   * Process view caching data.
   */
  private function processViewCaching($view_name, $view_data) {
    // Views with only master display.
    if (!isset($view_data['displays']) || (count($view_data['displays']) == 0)) {
      if ($view_data['default'] == 'none') {
        $this->registry->views_without_output_caching[] = $view_name;
      }
    } else {
      // If all the displays are default, consolidate.
      $all_default_displays = !array_filter($view_data['displays'], function($lifespan) {
        return $lifespan != 'default';
      });
      
      if ($all_default_displays) {
        if ($view_data['default'] == 'none') {
          $this->registry->views_without_output_caching[] = $view_name;
        }
      } else {
        $uncached_view_displays = array();
        foreach ($view_data['displays'] as $display_name => $display_data) {
          if ($display_data == 'none' || ($display_data == 'default' && $view_data['default'] == 'none')) {
            $uncached_view_displays[] = $display_name;
          }
        }
        if (!empty($uncached_view_displays)) {
          $this->registry->views_without_output_caching[] = $view_name . ' (' . implode(', ', $uncached_view_displays) . ')';
        }
      }
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
