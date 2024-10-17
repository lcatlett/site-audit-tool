<?php

/**
 * @file
 * Contains \SiteAudit\Check\Database\Collation.
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;

/**
 * Provides the Database Collation check.
 */
class DatabaseCollation extends SiteAuditCheckBase
{

  /**
   * {@inheritdoc}.
   */
  public function getId()
  {
    return 'database_collation';
  }

  /**
   * {@inheritdoc}.
   */
  public function getLabel()
  {
    return $this->t('Collations');
  }

  /**
   * {@inheritdoc}.
   */
  public function getDescription()
  {
    return $this->t("Check to see if there are any tables that aren't using UTF-8.");
  }

  /**
   * {@inheritdoc}.
   */
  public function getReportId()
  {
    return 'database';
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
    return $this->simpleKeyValueList($this->t('Table Name'), $this->t('Collation'), $this->registry->collation_tables);
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultPass()
  {
    return $this->t('Every table is using UTF-8.');
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultWarn()
  {
    return $this->getResultInfo();
  }

  /**
   * {@inheritdoc}.
   */
  public function getAction()
  {
    if ($this->getScore() == SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN) {
      return $this->t('In MySQL, use the command "!command" to convert the affected tables. Of course, test first and ensure your data will not be negatively affected.', array(
        '!command' => 'ALTER TABLE table_name CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;',
      ));
    }
  }

  /**
   * {@inheritdoc}.
   */
  public function calculateScore()
  {
    try {
      $this->registry->collation_tables = [];
      $connection = $this->getDatabaseConnection();
      $result = $this->getCollationQuery($connection);

      if ($result === FALSE) {
        throw new \Exception('Query execution failed.');
      }

      $count = 0;
      $warn = FALSE;
      
      if ($this->isDrupal7()) {
        while ($row = $result->fetchAssoc()) {
          $this->processRow($row, $count, $warn);
        }
      } else {
        foreach ($result as $row) {
          $this->processRow($row, $count, $warn);
        }
      }

      if ($count === 0) {
        return SiteAuditCheckBase::AUDIT_CHECK_SCORE_PASS;
      }
      if ($warn) {
        return SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN;
      }
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_INFO;
    } catch (\Exception $e) {
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_FAIL;
    }
  }

  /**
   * Process a single row of the collation query result.
   *
   * @param array $row
   *   The row data.
   * @param int $count
   *   The count of non-UTF-8 tables.
   * @param bool $warn
   *   Whether to set a warning flag.
   */
  protected function processRow($row, &$count, &$warn)
  {
    // Skip odd utf8 variants we might not know about explicitly
    if (strpos($row['collation'], 'utf8') !== FALSE) {
      return;
    }
    $count++;
    $this->registry->collation_tables[$row['name']] = $row['collation'];
    // Special case for old imports.
    if ($row['collation'] == 'latin1_swedish_ci') {
      $warn = TRUE;
    }
  }

  /**
   * Get the database connection.
   *
   * @return \Drupal\Core\Database\Connection|\DatabaseConnection
   *   The database connection.
   */
  protected function getDatabaseConnection()
  {
    if ($this->isDrupal7()) {
      return db_query('SELECT DATABASE()')->fetchField();
    } else {
      return \Drupal::database();
    }
  }

  /**
   * Get the collation query.
   *
   * @param mixed $connection
   *   The database connection.
   *
   * @return mixed
   *   The collation query result.
   */
  protected function getCollationQuery($connection)
  {
    if ($this->isDrupal7()) {
      $database_name = $this->getDatabaseName();
      return db_query("SELECT TABLE_NAME AS name, TABLE_COLLATION AS collation FROM information_schema.TABLES WHERE TABLE_SCHEMA = :database AND TABLE_COLLATION NOT IN ('utf8_general_ci', 'utf8_unicode_ci', 'utf8_bin', 'utf8mb4_general_ci')", [':database' => $database_name]);
    } else {
      $query = $connection->select('information_schema.TABLES', 'ist');
      $query->addField('ist', 'TABLE_NAME', 'name');
      $query->addField('ist', 'TABLE_COLLATION', 'collation');
      $query->condition('ist.TABLE_COLLATION', ['utf8_general_ci', 'utf8_unicode_ci', 'utf8_bin', 'utf8mb4_general_ci'], 'NOT IN');
      $query->condition('ist.table_schema', $this->getDatabaseName());
      return $query->execute();
    }
  }

  /**
   * Get the current database name.
   *
   * @return string
   *   The name of the current database.
   */
  protected function getDatabaseName()
  {
    if ($this->isDrupal7()) {
      return db_query('SELECT DATABASE()')->fetchField();
    } else {
      return \Drupal::database()->getConnectionOptions()['database'];
    }
  }

  /**
   * Check if the current Drupal version is 7.
   *
   * @return bool
   *   TRUE if Drupal 7, FALSE otherwise.
   */
  protected function isDrupal7()
  {
    return version_compare(VERSION, '8.0', '<');
  }
}
