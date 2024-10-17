<?php

/**
 * @file
 * Contains Drupal\site_audit\Plugin\SiteAuditCheck\StatusSystem
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;
use SiteAudit\Util\RenderHelper;

/**
 * Provides the StatusSystem Check.
 */
class StatusSystem extends SiteAuditCheckBase
{

  /**
   * {@inheritdoc}.
   */
  public function getId()
  {
    return 'status_system';
  }

  /**
   * {@inheritdoc}.
   */
  public function getLabel()
  {
    return $this->t('System Status');
  }

  /**
   * {@inheritdoc}.
   */
  public function getDescription()
  {
    return $this->t("Drupal's status report.");
  }

  /**
   * {@inheritdoc}.
   */
  public function getReportId()
  {
    return 'status';
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultFail()
  {
    return $this->getResultPass();
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultInfo() {}

  /**
   * {@inheritdoc}.
   */
  public function getResultPass()
  {
    $items = array();
    foreach ($this->registry->requirements as $requirement) {
      // Default to REQUIREMENT_INFO if no severity is set.
      if (!isset($requirement['severity'])) {
        $requirement['severity'] = REQUIREMENT_INFO;
      }

      // Title: severity - value.
      if ($requirement['severity'] == REQUIREMENT_INFO) {
        $class = 'info';
        $severity = 'Info';
      } elseif ($requirement['severity'] == REQUIREMENT_OK) {
        $severity = 'Ok';
        $class = 'success';
      } elseif ($requirement['severity'] == REQUIREMENT_WARNING) {
        $severity = 'Warning';
        $class = 'warning';
      } elseif ($requirement['severity'] == REQUIREMENT_ERROR) {
        $severity = 'Error';
        $class = 'error';
      }

      if ($this->registry->html) {
        $value = isset($requirement['value']) && $requirement['value'] ? $requirement['value'] : '&nbsp;';

        $item = array(
          'title' => $requirement['title'],
          'severity' => $severity,
          'value' => $value,
          'class' => $class,
        );
        foreach ($item as $key => $value) {
          $item[$key] = RenderHelper::render($value);
        }
      } else {
        $item = [
          'title' => RenderHelper::render($requirement['title']),
          'severity' => $severity,
          'value' => isset($requirement['value']) ? RenderHelper::render($requirement['value']) : ''
        ];
      }
      $items[] = $item;
    }

    if ($this->registry->html) {
      $ret_val = '<table class="table table-condensed">';
      $ret_val .= '<thead><tr><th>' . dt('Title') . '</th><th>' . dt('Severity') . '</th><th>' . dt('Value') . '</th></thead>';
      $ret_val .= '<tbody>';
      foreach ($items as $item) {
        $ret_val .= '<tr class="' . $item['class'] . '">';
        $ret_val .= '<td>' . $item['title'] . '</td>';
        $ret_val .= '<td>' . $item['severity'] . '</td>';
        $ret_val .= '<td>' . $item['value'] . '</td>';
        $ret_val .= '</tr>';
      }
      $ret_val .= '</tbody>';
      $ret_val .= '</table>';
    } else {
      $ret_val = "| Title | Severity | Value |\n";
      $ret_val .= "|-------|----------|-------|\n";
      foreach ($items as $item) {
        $value = str_replace("\n", " ", $item['value']); // Replace newlines with spaces for Markdown
        $ret_val .= "| " . $item['title'] . " | " . $item['severity'] . " | " . $value . " |\n";
      }
    }
    return $ret_val;
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultWarn()
  {
    return $this->getResultPass();
  }

  /**
   * {@inheritdoc}.
   */
  public function getAction() {}

  /**
   * {@inheritdoc}.
   */
  public function calculateScore()
  {
    if ($this->isDrupal7()) {
      return $this->calculateScoreDrupal7();
    } else {
      return $this->calculateScoreDrupal8Plus();
    }
  }

  /**
   * Calculate score for Drupal 7.
   */
  private function calculateScoreDrupal7()
  {
    // Load .install files
    include_once DRUPAL_ROOT . '/includes/install.inc';
    drupal_load_updates();

    // Check run-time requirements and status information.
    $this->registry->requirements = module_invoke_all('requirements', 'runtime');
    drupal_alter('requirements', $this->registry->requirements);
    uasort($this->registry->requirements, [$this, 'sortRequirements']);

    return $this->calculateFinalScore();
  }

  /**
   * Calculate score for Drupal 8+.
   */
  private function calculateScoreDrupal8Plus()
  {
    // Load .install files
    include_once DRUPAL_ROOT . '/core/includes/install.inc';
    drupal_load_updates();

    // Check run-time requirements and status information.
    $this->registry->requirements = \Drupal::moduleHandler()->invokeAll('requirements', ['runtime']);
    \Drupal::moduleHandler()->alter('requirements', $this->registry->requirements);
    uasort($this->registry->requirements, [$this, 'sortRequirements']);

    return $this->calculateFinalScore();
  }

  /**
   * Calculate the final score based on requirements.
   */
  private function calculateFinalScore()
  {
    $this->percentOverride = 0;
    $requirements_with_severity = array_filter($this->registry->requirements, function ($value) {
      return isset($value['severity']);
    });
    $score_each = 100 / count($requirements_with_severity);

    $worst_severity = REQUIREMENT_INFO;
    foreach ($this->registry->requirements as $requirement) {
      if (isset($requirement['severity'])) {
        $worst_severity = max($worst_severity, $requirement['severity']);
        if ($requirement['severity'] == REQUIREMENT_WARNING) {
          $this->percentOverride += $score_each / 2;
        } elseif ($requirement['severity'] != REQUIREMENT_ERROR) {
          $this->percentOverride += $score_each;
        }
      }
    }

    $this->percentOverride = min($this->percentOverride, 100);
    return $this->percentOverride;
  }

  protected function isDrupal7()
  {
    return version_compare(VERSION, '8.0', '<');
  }

  protected function sortRequirements($a, $b)
  {
    if (!isset($a['weight'])) {
      $a['weight'] = 0;
    }
    if (!isset($b['weight'])) {
      $b['weight'] = 0;
    }

    if ($a['weight'] == $b['weight']) {
      return strcasecmp($a['title'], $b['title']);
    }
    return ($a['weight'] < $b['weight']) ? -1 : 1;
  }
}
