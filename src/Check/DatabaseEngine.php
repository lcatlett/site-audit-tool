<?php
/**
 * @file
 * Contains Drupal\site_audit\Plugin\SiteAuditCheck\DatabaseEngine
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;

/**
 * Provides the Database Engine check.
 */
class DatabaseEngine extends SiteAuditCheckBase {

  /**
   * {@inheritdoc}.
   */
  public function getId() {
    return 'database_engine';
  }

  /**
   * {@inheritdoc}.
   */
  public function getLabel() {
    return $this->t('Storage Engines');
  }

  /**
   * {@inheritdoc}.
   */
  public function getDescription() {
    return $this->t("Check to see if there are any tables that aren't using InnoDB.");
  }

  /**
   * {@inheritdoc}.
   */
  public function getReportId() {
    return 'database';
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultFail() {}

  /**
   * {@inheritdoc}.
   */
  public function getResultInfo() {
    return $this->simpleKeyValueList($this->t('Table Name'), $this->t('Engine'), $this->registry->engine_tables);
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultPass() {
    return $this->t('Every table is using InnoDB.');
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultWarn() {
    return $this->getResultInfo();
  }

  /**
   * {@inheritdoc}.
   */
  public function getAction() {
    if ($this->getScore() == SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN) {
      return $this->t('In MySQL, run "ALTER TABLE table_name ENGINE=InnoDB;" on the affected tables.');
    }
  }

  /**
   * {@inheritdoc}.
   */
  public function calculateScore() {
    $this->registry->engine_tables = array();
    try {
      if ($this->isDrupal7()) {
        $sql = "SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db_name AND ENGINE != 'InnoDB'";
        $args = array(':db_name' => $this->getDatabaseName());
        $result = db_query($sql, $args);
      }
      else {
        $connection = \Drupal::database();
        $result = $connection->query("SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db_name AND ENGINE != 'InnoDB'", [
          ':db_name' => $connection->getConnectionOptions()['database'],
        ]);
      }

      $count = 0;
      foreach ($result as $row) {
        $count++;
        $this->registry->engine_tables[$row->name] = $row->engine;
      }

      if ($count === 0) {
        return SiteAuditCheckBase::AUDIT_CHECK_SCORE_PASS;
      }
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN;
    }
    catch (\Exception $e) {
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_FAIL;
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

  /**
   * Get the current database name.
   *
   * @return string
   *   The name of the current database.
   */
  protected function getDatabaseName() {
    if ($this->isDrupal7()) {
      return db_query('SELECT DATABASE()')->fetchField();
    }
    else {
      return \Drupal::database()->getConnectionOptions()['database'];
    }
  }
}
