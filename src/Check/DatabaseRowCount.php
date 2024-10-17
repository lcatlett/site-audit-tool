<?php
/**
 * @file
 * Contains Drupal\site_audit\Plugin\SiteAuditCheck\DatabaseRowCount
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;

/**
 * Provides the Database Row Count check.
 */
class DatabaseRowCount extends SiteAuditCheckBase {

  /**
   * {@inheritdoc}.
   */
  public function getId() {
    return 'database_row_count';
  }

  /**
   * {@inheritdoc}.
   */
  public function getLabel() {
    return $this->t('Tables with at least 1000 rows');
  }

  /**
   * {@inheritdoc}.
   */
  public function getDescription() {
    return $this->t("Return list of all tables with at least 1000 rows in the database.");
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
    if (empty($this->registry->rows_by_table)) {
      return $this->t('No tables with more than 1000 rows.');
    }
    return $this->simpleKeyValueList($this->t('Table Name'), $this->t('Rows'), $this->registry->rows_by_table);
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultPass() {}

  /**
   * {@inheritdoc}.
   */
  public function getResultWarn() {
    return $this->getResultInfo();
  }

  /**
   * {@inheritdoc}.
   */
  public function getAction() {}

  /**
   * {@inheritdoc}.
   */
  public function calculateScore() {
    $this->registry->rows_by_table = array();
    $warning = FALSE;

    try {
      if ($this->isDrupal7()) {
        $sql = "SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db_name AND TABLE_ROWS > 1000 ORDER BY TABLE_ROWS DESC";
        $args = array(':db_name' => $this->getDatabaseName());
        $result = db_query($sql, $args);
      }
      else {
        $connection = \Drupal::database();
        $query = $connection->select('information_schema.TABLES', 'ist');
        $query->fields('ist', array('TABLE_NAME', 'TABLE_ROWS'));
        $query->condition('ist.TABLE_ROWS', 1000, '>');
        $query->condition('ist.table_schema', $connection->getConnectionOptions()['database']);
        $query->orderBy('TABLE_ROWS', 'DESC');
        $result = $query->execute();
      }

      foreach ($result as $row) {
        if ($row->TABLE_ROWS > 1000) {
          $warning = TRUE;
        }
        $this->registry->rows_by_table[$row->TABLE_NAME] = $row->TABLE_ROWS;
      }

      if ($warning) {
        return SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN;
      }
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_INFO;
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
