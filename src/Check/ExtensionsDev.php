<?php
/**
 * @file
 * Contains Drupal\site_audit\Plugin\SiteAuditCheck\ExtensionsDev
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;
use SiteAudit\Util\SiteAuditEnvironment;

/**
 * Provides the ExtensionsDev Check.
 */
class ExtensionsDev extends SiteAuditCheckBase {

  /**
   * {@inheritdoc}.
   */
  public function getId() {
    return 'extensions_dev';
  }

  /**
   * {@inheritdoc}.
   */
  public function getLabel() {
    return $this->t('Development');
  }

  /**
   * {@inheritdoc}.
   */
  public function getDescription() {
    return $this->t("Check for enabled development modules.");
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
  public function getResultInfo() {
    return $this->getResultWarn();
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultPass() {
    return $this->t('No enabled development extensions were detected; no action required.');
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultWarn() {
    if (empty($this->registry->extensions_dev)) {
      return $this->t('No development modules were detected.');
    }
    
    $ret_val = $this->t('The following development modules(s) are currently enabled: @list', array(
      '@list' => implode(', ', array_keys($this->registry->extensions_dev)),
    ));
    $show_table = !SiteAuditEnvironment::isDev();

    if ($this->registry->detail && $show_table) {
      $data = $this->rowsToKeyValueList($this->registry->extensions_dev);
      $ret_val .= $this->linebreak();
      $ret_val .= $this->simpleKeyValueList($this->t('Name'), $this->t('Reason'), $data);
    }
    return $ret_val;
  }

  /**
   * {@inheritdoc}.
   */
  public function getAction() {
    if ($this->getScore() == SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN) {
      $show_action = TRUE;
      if (SiteAuditEnvironment::isDev()) {
        $show_action = FALSE;
      }
      if ($show_action) {
        return $this->t('Disable development modules for increased stability, security and performance in the Live (production) environment.');
      }
    }
  }

  /**
   * {@inheritdoc}.
   */
  public function calculateScore() {
    $dev_modules = $this->getExtensions();
    $this->registry->extensions_dev = [];

    if ($this->isDrupal7()) {
      $modules = module_list();
      foreach ($dev_modules as $dev_module => $reason) {
        if (isset($modules[$dev_module])) {
          $this->registry->extensions_dev[$dev_module] = $reason;
        }
      }
    } else {
      $moduleHandler = \Drupal::service('module_handler');
      $modules = $moduleHandler->getModuleList();
      foreach ($dev_modules as $dev_module => $reason) {
        if (isset($modules[$dev_module])) {
          $this->registry->extensions_dev[$dev_module] = $reason;
        }
      }
    }

    if (!empty($this->registry->extensions_dev)) {
      return SiteAuditEnvironment::isDev() ? SiteAuditCheckBase::AUDIT_CHECK_SCORE_INFO : SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN;
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

  /**
   * Get a list of development extension names and reasons.
   *
   * @return array
   *   Keyed by module machine name, value is explanation.
   */
  public function getExtensions() {
    $developer_modules = array(
      'ipsum' => $this->t('Development utility to generate fake content.'),
      'testmodule' => $this->t('Internal test module.'),
      // Examples module.
      'block_example' => $this->t('Development examples.'),
      'cache_example' => $this->t('Development examples.'),
      'config_entity_example' => $this->t('Development examples.'),
      'content_entity_example' => $this->t('Development examples.'),
      'dbtng_example' => $this->t('Development examples.'),
      'email_example' => $this->t('Development examples.'),
      'examples' => $this->t('Development examples.'),
      'field_example' => $this->t('Development examples.'),
      'field_permission_example' => $this->t('Development examples.'),
      'file_example' => $this->t('Development examples.'),
      'js_example' => $this->t('Development examples.'),
      'node_type_example' => $this->t('Development examples.'),
      'page_example' => $this->t('Development examples.'),
      'phpunit_example' => $this->t('Development examples.'),
      'simpletest_example' => $this->t('Development examples.'),
      'tablesort_example' => $this->t('Development examples.'),
      'tour_example' => $this->t('Development examples.'),
    );

    // From http://drupal.org/project/admin_menu admin_menu.inc in function
    // _admin_menu_developer_modules().
    $admin_menu_developer_modules = array(
      'admin_devel' => $this->t('Debugging utility; degrades performance.'),
      'cache_disable' => $this->t('Development utility and performance drain; degrades performance.'),
      'coder' => $this->t('Debugging utility; potential security risk and unnecessary performance hit.'),
      'content_copy' => $this->t('Development utility; unnecessary overhead.'),
      'context_ui' => $this->t('Development user interface; unnecessary overhead.'),
      'debug' => $this->t('Debugging utility; potential security risk, unnecessary overhead.'),
      'delete_all' => $this->t('Development utility; potentially dangerous.'),
      'demo' => $this->t('Development utility for sandboxing.'),
      'devel' => $this->t('Debugging utility; degrades performance and potential security risk.'),
      'devel_node_access' => $this->t('Development utility; degrades performance and potential security risk.'),
      'devel_themer' => $this->t('Development utility; degrades performance and potential security risk.'),
      'field_ui' => $this->t(
      'Development user interface; allows privileged users to change site
structure which can lead to data inconsistencies. Best practice is to
store Content Types in code and deploy changes instead of allowing
editing in live environments.'),
      'fontyourface_ui' => $this->t('Development user interface; unnecessary overhead.'),
      'form_controller' => $this->t('Development utility; unnecessary overhead.'),
      'imagecache_ui' => $this->t('Development user interface; unnecessary overhead.'),
      'journal' => $this->t('Development utility; unnecessary overhead.'),
      'l10n_client' => $this->t('Development utility; unnecessary overhead.'),
      'l10n_update' => $this->t('Development utility; unnecessary overhead.'),
      'macro' => $this->t('Development utility; unnecessary overhead.'),
      'rules_admin' => $this->t('Development user interface; unnecessary overhead.'),
      'stringoverrides' => $this->t('Development utility.'),
      'trace' => $this->t('Debugging utility; degrades performance and potential security risk.'),
      'upgrade_status' => $this->t('Development utility for performing a major Drupal core update; should removed after use.'),
      'user_display_ui' => $this->t('Development user interface; unnecessary overhead.'),
      'util' => $this->t('Development utility; unnecessary overhead, potential security risk.'),
      'views_ui' => $this->t(
      'Development UI; allows privileged users to change site structure which
can lead to performance problems or inconsistent behavior. Best practice
is to store Views in code and deploy changes instead of allowing editing
in live environments.'),
      'views_theme_wizard' => $this->t('Development utility; unnecessary overhead, potential security risk.'),
    );

    return array_merge($admin_menu_developer_modules, $developer_modules);
  }

}
