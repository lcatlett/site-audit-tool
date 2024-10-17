<?php

namespace Drush\Commands\site_audit_tool;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Consolidation\OutputFormatters\StructuredData\RowsOfFieldsWithMetadata;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use SiteAudit\ChecksRegistry;
use SiteAudit\SiteAuditCheckBase;
use SiteAudit\SiteAuditCheckInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Consolidation\AnnotatedCommand\AnnotatedCommand;

/**
 * Edit this file to reflect your organization's needs.
 * This class provides Drush commands for Site Audit Tool.
 * It now supports JSON, HTML, and Markdown output formats.
 */
class SiteAuditCommands extends DrushCommands
{
    /**
     * We don't use @hook init here, because Drush 8 does not call it. Instead,
     * we call our init method explicity early in every command method.
     */
    public function init()
    {
        if (!class_exists('\SiteAudit\SiteAuditCheckBase')) {
            $loader = new \Composer\Autoload\ClassLoader();
            $loader->addPsr4('SiteAudit\\', __DIR__ . '/src');
            $loader->register();
        }
    }

    /**
     * Show Site Audit version.
     *
     * @command audit:version
     * @aliases site-audit-version
     * @table-style compact
     * @list-delimiter :
     * @field-labels
     *   audit-version: Site Audit Tool version
     *
     * @return \Consolidation\OutputFormatters\StructuredData\PropertyList
     *
     */
    public function version($options = ['format' => 'table'])
    {
        $version = file_get_contents(__DIR__ . '/VERSION');
        return new PropertyList(['audit-version' => $version]);
    }

    /**
     * @command audit:reports
     * @aliases aa
     * @option format The output format (json, html, or markdown).
     * @option detail Show detailed results.
     * @option vendor Vendor-specific checks to include.
     * @option skip Checks to skip.
     * @usage drush audit:reports --format=json
     * @usage drush audit:reports --markdown
     *
     * @param array $options
     * @return array
     *
     * @bootstrap full
     *
     * Combine all of our reports
     */
    public function auditReports($options = ['format' => 'json', 'html' => false, 'json' => false, 'markdown' => false, 'detail' => false, 'vendor' => '', 'skip' => [], 'env-vars' => false])
    {
        $this->init();

        $settings_excludes = \Drupal::config('site_audit')->get('opt_out');
        $skipped = [];

        // The skip parameter is almost always an array, even when the single
        // value is a list of options.
        if (isset($options['skip'])) {
            if (is_array($options['skip']) && count($options['skip']) == 1) {
                $skipped = explode(',', $options['skip'][0]);
            } elseif (is_string($options['skip'])) {
                $skipped = explode(',', $options['skip']);
            } elseif (is_array($options['skip'])) {
                $skipped = $options['skip'];
            }
        }

        if (!empty($settings_excludes)) {
            $settings_excludes = array_keys($settings_excludes);
            $skipped = array_merge($settings_excludes, $skipped);
        }

        $checks = $this->interimInstantiateChecks($this->createRegistry($options), $skipped);
        $result = $this->interimBuildReports($checks);

        // Add database size information to the result
        $dbSizeInfo = $this->extractDatabaseSizeInfo($result);
        if ($dbSizeInfo) {
            $result['database_size_info'] = $dbSizeInfo;
        }

        if (!empty($options['json'])) {
            print json_encode($result, JSON_PRETTY_PRINT);
            return null;
        }

        if (!empty($options['html'])) {
            print $this->generateHtmlReport($result);
            return null;
        }

        if (!empty($options['markdown'])) {
            $markdown = $this->generateMarkdownReport($result, !empty($options['env-vars']));
            print $markdown;
            return null;
        }

        // Otherwise, use the output formatter
        return $result;
    }

    /**
     * @hook option audit:reports
     */
    public function optionEnvVars(\Consolidation\AnnotatedCommand\AnnotatedCommand $command)
    {
        $command->addOption(
            'env-vars',
            null,
            InputOption::VALUE_NONE,
            'Include environment variables report.'
        );
    }
    /**
     * @command audit:best-practices
     * @aliases audit_best_practices,abp
     * @field-labels
     *     label: Label
     *     description: Description
     *     result: Result
     *     action: Action
     *     score: Score
     * @default-table-fields label,result
     * @return RowsOfFieldsWithMetadata
     *
     * @bootstrap full
     *
     * Demonstrates a trivial command that takes a single required parameter.
     */
    public function auditBestPractices(
        $options = [
            'format' => 'json',
            'html' => false,
            'detail' => false,
            'vendor' => '',
        ]
    ) {
        return $this->singleReport('best_practices', $options);
    }

    /**
     * @command audit:extensions
     * @aliases audit_extensions,ae
     * @field-labels
     *     label: Label
     *     description: Description
     *     result: Result
     *     action: Action
     *     score: Score
     * @default-table-fields label,result
     * @return RowsOfFieldsWithMetadata
     *
     * @bootstrap full
     *
     * Audit extensions (modules and themes).
     */
    public function auditExtensions(
        $options = [
            'format' => 'json',
            'html' => false,
            'detail' => false,
            'vendor' => '',
        ]
    ) {
        return $this->singleReport('extensions', $options);
    }

    /**
     * @command audit:block
     * @aliases audit_block,ab
     * @field-labels
     *     label: Label
     *     description: Description
     *     result: Result
     *     action: Action
     *     score: Score
     * @default-table-fields label,result
     * @return RowsOfFieldsWithMetadata
     *
     * @bootstrap full
     *
     * Audit blocks.
     */
    public function auditBlock(
        $options = [
            'format' => 'json',
            'html' => false,
            'detail' => false,
            'vendor' => '',
        ]
    ) {
        return $this->singleReport('block', $options);
    }

    /**
     * @command audit:cache
     * @aliases audit_cache,ac
     * @field-labels
     *     label: Label
     *     description: Description
     *     result: Result
     *     action: Action
     *     score: Score
     * @default-table-fields label,result
     * @return RowsOfFieldsWithMetadata
     *
     * @bootstrap full
     *
     * Audit blocks.
     */
    public function auditCache(
        $options = [
            'format' => 'json',
            'html' => false,
            'detail' => false,
            'vendor' => '',
        ]
    ) {
        return $this->singleReport('cache', $options);
    }

    /**
     * @command audit:cron
     * @aliases audit_cron,acr
     * @field-labels
     *     label: Label
     *     description: Description
     *     result: Result
     *     action: Action
     *     score: Score
     * @default-table-fields label,result
     * @return RowsOfFieldsWithMetadata
     *
     * @bootstrap full
     *
     * Audit cron.
     */
    public function auditCron(
        $options = [
            'format' => 'json',
            'html' => false,
            'detail' => false,
            'vendor' => '',
        ]
    ) {
        return $this->singleReport('cron', $options);
    }

    /**
     * @command audit:database
     * @aliases audit_database,ad
     * @field-labels
     *     label: Label
     *     description: Description
     *     result: Result
     *     action: Action
     *     score: Score
     * @default-table-fields label,result
     * @return RowsOfFieldsWithMetadata
     *
     * @bootstrap full
     *
     * Audit database.
     */
    public function auditDatabase(
        $options = [
            'format' => 'json',
            'html' => false,
            'detail' => false,
            'vendor' => '',
        ]
    ) {
        return $this->singleReport('database', $options);
    }

    /**
     * @command audit:security
     * @aliases audit_security,asec
     * @field-labels
     *     label: Label
     *     description: Description
     *     result: Result
     *     action: Action
     *     score: Score
     * @default-table-fields label,result
     * @return RowsOfFieldsWithMetadata
     *
     * @bootstrap full
     *
     * Audit blocks.
     */
    public function auditSecurity(
        $options = [
            'format' => 'json',
            'html' => false,
            'detail' => false,
            'vendor' => '',
        ]
    ) {
        return $this->singleReport('security', $options);
    }

    /**
     * @command audit:users
     * @aliases audit_users,au
     * @field-labels
     *     label: Label
     *     description: Description
     *     result: Result
     *     action: Action
     *     score: Score
     * @default-table-fields label,result
     * @return RowsOfFieldsWithMetadata
     *
     * @bootstrap full
     *
     * Audit blocks.
     */
    public function auditUsers(
        $options = [
            'format' => 'json',
            'html' => false,
            'detail' => false,
            'vendor' => '',
        ]
    ) {
        return $this->singleReport('users', $options);
    }

    /**
     * @command audit:views
     * @aliases audit_views,av
     * @field-labels
     *     label: Label
     *     description: Description
     *     result: Result
     *     action: Action
     *     score: Score
     * @default-table-fields label,result
     * @return RowsOfFieldsWithMetadata
     *
     * @bootstrap full
     *
     * Audit blocks.
     */
    public function auditViews(
        $options = [
            'format' => 'json',
            'html' => false,
            'detail' => false,
            'vendor' => '',
        ]
    ) {
        return $this->singleReport('views', $options);
    }

    /**
     * @command audit:watchdog
     * @aliases audit_watchdog,aw
     * @field-labels
     *     label: Label
     *     description: Description
     *     result: Result
     *     action: Action
     *     score: Score
     * @default-table-fields label,result
     * @return RowsOfFieldsWithMetadata
     *
     * @bootstrap full
     *
     * Audit blocks.
     */
    public function auditWatchdog(
        $options = [
            'format' => 'json',
            'html' => false,
            'detail' => false,
            'vendor' => '',
        ]
    ) {
        return $this->singleReport('watchdog', $options);
    }

    /**
     * Generate a single report for one of the individual report commands above.
     *
     * @param string $reportId
     *   The id of the report to generate. @see interimReportsList
     * @param array $options
     *   The commandline options
     * @return RowsOfFieldsWithMetadata
     *   The generated report
     */
    protected function singleReport($reportId, $options)
    {
        $this->init();
        $settings_excludes = \Drupal::config('site_audit')->get('opt_out');
        $checks = $this->interimInstantiateChecks($this->createRegistry($options), $settings_excludes);
        $reportChecks = $this->checksForReport($reportId, $checks);

        // Temporary code to be thrown away
        $report = $this->interimReport($this->interimReportLabel($reportId), $reportChecks);

        return (new RowsOfFieldsWithMetadata($report))
            ->setDataKey('checks');
    }

    /**
     * Create the 'registry' object used in all checks
     *
     * @param array $options
     *   The commandline options
     *
     * @return stdClass
     *   The registry object
     */
    protected function createRegistry($options = [])
    {
        $options += [
            'vendor' => '',
            'html' => false,
            'detail' => false,
        ];

        $registry = new \stdClass();

        // We'd rather 'registry' be a class with an interface, but
        // since we do not have that, we will simply add these options
        // as attributes of the stdClass to serve as a replacement for
        // drush_get_option().
        $registry->vendor = $options['vendor'];
        $registry->html = $options['html'];
        $registry->detail = $options['detail'];

        $registry->checksList = new ChecksRegistry();

        return $registry;
    }

    /**
     * Return only those checks from the provided list that match the specified
     * report id.
     *
     * @param string $reportId
     * @param SiteAuditCheckInterface[] $checks
     *
     * @return SiteAuditCheckInterface[]
     */
    protected function checksForReport($reportId, array $checks)
    {
        $result = [];

        foreach ($checks as $check) {
            if ($reportId == $check->getReportId()) {
                $result[] = $check;
            }
        }

        return $result;
    }

    /**
     * Instantiates all available checks.
     *
     * Interim implementation. Ideally would be factored into another class.
     *
     * @param stdClass $registry
     *   The registry used by all checks
     * @param array $excludes
     *   Array of all tests which should be excluded.
     *
     * @return SiteAuditCheckInterface[]
     */
    protected function interimInstantiateChecks($registry, $excludes = [])
    {
        $checks = [

            // best_practices
            new \SiteAudit\Check\BestPracticesFast404($registry, [], $excludes),
            new \SiteAudit\Check\BestPracticesFolderStructure($registry, [], $excludes),
            new \SiteAudit\Check\BestPracticesMultisite($registry, [], $excludes),
            new \SiteAudit\Check\BestPracticesSettings($registry, [], $excludes),
            new \SiteAudit\Check\BestPracticesServices($registry, [], $excludes),
            new \SiteAudit\Check\BestPracticesSites($registry, [], $excludes),
            new \SiteAudit\Check\BestPracticesSitesDefault($registry, [], $excludes),
            new \SiteAudit\Check\BestPracticesSitesSuperfluous($registry, [], $excludes),

            // block
            new \SiteAudit\Check\BlockEnabled($registry, [], $excludes),

            // cache
            new \SiteAudit\Check\CacheBinsAll($registry, [], $excludes),
            new \SiteAudit\Check\CacheBinsDefault($registry, [], $excludes),
            new \SiteAudit\Check\CacheBinsUsed($registry, [], $excludes),
            new \SiteAudit\Check\CachePageExpire($registry, [], $excludes),
            new \SiteAudit\Check\CachePreprocessCSS($registry, [], $excludes),
            new \SiteAudit\Check\CachePreprocessJS($registry, [], $excludes),

            // cron
            new \SiteAudit\Check\CronEnabled($registry, [], $excludes),
            new \SiteAudit\Check\CronLast($registry, [], $excludes),

            // database
            new \SiteAudit\Check\DatabaseSize($registry, [], $excludes),
            new \SiteAudit\Check\DatabaseCollation($registry, [], $excludes),
            new \SiteAudit\Check\DatabaseEngine($registry, [], $excludes),
            new \SiteAudit\Check\DatabaseRowCount($registry, [], $excludes),

            // extensions
            new \SiteAudit\Check\ExtensionsCount($registry, [], $excludes),
            new \SiteAudit\Check\ExtensionsDev($registry, [], $excludes),
            new \SiteAudit\Check\ExtensionsDuplicate($registry, [], $excludes),
            new \SiteAudit\Check\ExtensionsUnrecommended($registry, [], $excludes),

            // security
            new \SiteAudit\Check\SecurityMenuRouter($registry, [], $excludes),

            // status
            new \SiteAudit\Check\StatusSystem($registry, [], $excludes),

            // user
            new \SiteAudit\Check\UsersBlockedNumberOne($registry, [], $excludes),
            new \SiteAudit\Check\UsersCountAll($registry, [], $excludes),
            new \SiteAudit\Check\UsersCountBlocked($registry, [], $excludes),
            new \SiteAudit\Check\UsersRolesList($registry, [], $excludes),
            new \SiteAudit\Check\UsersWhoIsNumberOne($registry, [], $excludes),

            // views
            new \SiteAudit\Check\ViewsCacheOutput($registry, [], $excludes),
            new \SiteAudit\Check\ViewsCacheResults($registry, [], $excludes),
            new \SiteAudit\Check\ViewsCount($registry, [], $excludes),
            new \SiteAudit\Check\ViewsEnabled($registry, [], $excludes),

            // watchdog
            new \SiteAudit\Check\Watchdog404($registry, [], $excludes),
            new \SiteAudit\Check\WatchdogAge($registry, [], $excludes),
            new \SiteAudit\Check\WatchdogCount($registry, [], $excludes),
            new \SiteAudit\Check\WatchdogEnabled($registry, [], $excludes),
            new \SiteAudit\Check\WatchdogPhp($registry, [], $excludes),
            new \SiteAudit\Check\WatchdogSyslog($registry, [], $excludes),

        ];

        return $checks;
    }

    /**
     * Return a list of all of the reports in an id => description
     *
     * Interim implementation. Ideally would be factored into another class.
     *
     * @return string[]
     */
    protected function interimReportsList()
    {
        return [
            'best_practices' => "Best practices",
            'block' => "Block",
            'cache' => "Drupal's caching settings",
            'cron' => "Cron",
            'database' => "Database",
            'extensions' => "Extensions",
            'front_end' => "Front End",
            'status' => "Status",
            'security' => "Security",
            'users' => "Users",
            'views' => "Views",
            'watchdog' => "Watchdog database logs",
        ];
    }

    /**
     * Given a report id, return the report label
     *
     * Interim implementation. Ideally would be factored into another class.
     *
     * @param string $reportId
     * @return string
     */
    protected function interimReportLabel($reportId)
    {
        $reports = $this->interimReportsList();

        return $reports[$reportId];
    }

    /**
     * Given a report id, return the legacy report key (used in the
     * site audit json results).
     *
     * Interim implementation. Ideally would be factored into another class.
     *
     * @param string $reportId
     * @return string
     */
    protected function interimReportKey($reportId)
    {
        // Convert from snake_case to CamelCase and append to SiteAuditReport
        return 'SiteAuditReport' . str_replace(' ', '', ucwords(str_replace('_', ' ', $reportId)));
    }

    /**
     * Create master report that contains all provided reports with headers.
     *
     * @param SiteAuditCheckInterface[] $checks
     * @return array
     */
    protected function interimBuildReports($checks)
    {
        $reportsList = $this->interimReportsList();

        foreach ($reportsList as $reportId => $label) {
            $key = $this->interimReportKey($reportId);
            $reportChecks = $this->checksForReport($reportId, $checks);
            if (!empty($reportChecks)) {
                $reports[$key] = $this->interimReport($label, $reportChecks);
            }
        }

        return [
            'time' => time(),
            'reports' => $reports,
        ];
    }

    /**
     * Create a single report using the same structure used by the 7.x-1.x
     * version of Site Audit
     *
     * @param SiteAuditCheckInterface[] $checks
     * @return array
     */
    protected function interimReport($label, $checks)
    {
        $score = 0;
        $max = 0;
        $checkResults = [];

        foreach ($checks as $check) {
            if ($check->getScore() != SiteAuditCheckBase::AUDIT_CHECK_SCORE_INFO) {
                $max += SiteAuditCheckBase::AUDIT_CHECK_SCORE_PASS;
                $score += $check->getScore();
            }
            $checkResults += $this->interimReportResults($check);
        }

        if ($max == 0) {
            $percent = 0;
        } else {
            $percent = ($score * 100) / $max;
        }

        return [
            "percent" => (int) $percent,
            "label" => $label,
            "checks" => $checkResults,
        ];
    }

    /**
     * Get the result for just one check
     *
     * @param SiteAuditCheckInterface $check
     * @return array
     */
    protected function interimReportResults(SiteAuditCheckInterface $check)
    {
        $checkName = $this->interimGetCheckName($check);
        return [
            $checkName => [
                "label" => $check->getLabel(),
                "description" => $check->getDescription(),
                "result" => $check->getResult(),
                "action" => $check->renderAction(),
                "score" => $check->getScore(),
            ],
        ];
    }

    /**
     * Convert the check to the legacy check name
     *
     * @param SiteAuditCheckInterface $check
     * @return string
     */
    protected function interimGetCheckName(SiteAuditCheckInterface $check)
    {
        $full_class_name = get_class($check);
        return str_replace('\\', '', $full_class_name);
    }

    protected function generateMarkdownReport($result, $includeEnvVars = false)
    {
        $markdown = "# Site Audit Report\n\n";
        $markdown .= "Generated on: " . date('Y-m-d H:i:s', $result['time']) . "\n\n";

        // Add environment information
        $markdown .= "## Environment Information\n\n";
        $markdown .= "| Key | Value |\n";
        $markdown .= "|-----|-------|\n";
        $markdown .= "| Environment | " . $this->getEnvironment() . " |\n";
        $markdown .= "| Site URI | " . $this->getSiteUri() . " |\n\n";

        // Add database size information
        $markdown .= "## Database Size Information\n\n";
        $markdown .= "| Metric | Value |\n";
        $markdown .= "|--------|-------|\n";

        // Extract information from the result string
        $resultLines = explode('<br>', $result['database_size_info']['result']);
        foreach ($resultLines as $line) {
            $parts = explode(': ', $line, 2);
            if (count($parts) == 2) {
                $markdown .= "| " . trim($parts[0]) . " | " . trim($parts[1]) . " |\n";
            }
        }

        $markdown .= "\n";

        if (isset($result['database_size_info']['score']) && $result['database_size_info']['score'] == SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN) {
            $markdown .= "**Warning:** " . ($result['database_size_info']['result'] ?? 'High cache table usage detected.') . "\n\n";
        }

        if (!empty($result['database_size_info']['action'])) {
            $markdown .= "**Recommended Action:** " . $result['database_size_info']['action'] . "\n\n";
        }

        // Add environment variables report if enabled
        if ($includeEnvVars) {
            // Todo: fix env-vars option
            //$markdown .= $this->generateEnvironmentVariablesReport();
        }

        foreach ($result['reports'] as $reportKey => $report) {
            $markdown .= "## " . $report['label'] . "\n\n";
            $markdown .= "Overall Score: " . $report['percent'] . "%\n\n";

            foreach ($report['checks'] as $checkKey => $check) {
                $markdown .= "### " . $check['label'] . "\n\n";
                $markdown .= "**Result:** " . $check['result'] . "\n\n";
                
                if (!empty($check['action'])) {
                    $markdown .= "**Action:** " . $check['action'] . "\n\n";
                }

                if (!empty($check['description'])) {
                    $markdown .= "**Description:** " . $check['description'] . "\n\n";
                }

                $markdown .= "**Score:** " . $this->getScoreEmoji($check['score']) . "\n\n";
            }
        }

        return $markdown;
    }

    protected function generateEnvironmentVariablesReport()
    {
        $markdown = "## Environment Variables Report\n\n";

        $envVars = $this->getAllEnvironmentVariables();
        $totalVars = count($envVars);
        $sensitiveVars = $this->countSensitiveVariables($envVars);

        $markdown .= "**Total Variables:** $totalVars\n\n";
        $markdown .= "**Potentially Sensitive Variables:** $sensitiveVars\n\n";

        $markdown .= "### Variable List\n\n";
        $markdown .= "| Variable | Value |\n";
        $markdown .= "|----------|-------|\n";

        foreach ($envVars as $key => $value) {
            $safeValue = $this->sanitizeValue($key, $value);
            $markdown .= "| $key | $safeValue |\n";
        }

        $markdown .= "\n";

        return $markdown;
    }

    protected function getAllEnvironmentVariables()
    {
        $envVars = getenv();
        ksort($envVars); // Sort variables alphabetically by key
        return $envVars;
    }

    protected function countSensitiveVariables($envVars)
    {
        $sensitiveKeywords = ['password', 'secret', 'token', 'key', 'api'];
        $count = 0;

        foreach ($envVars as $key => $value) {
            foreach ($sensitiveKeywords as $keyword) {
                if (stripos($key, $keyword) !== false) {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    protected function sanitizeValue($key, $value)
    {
        $sensitiveKeywords = ['password', 'secret', 'token', 'key', 'api'];
        
        foreach ($sensitiveKeywords as $keyword) {
            if (stripos($key, $keyword) !== false) {
                return '[REDACTED]';
            }
        }

        // Escape pipe characters to prevent breaking the markdown table
        return str_replace('|', '\\|', $value);
    }

    protected function getScoreEmoji($score)
    {
        switch ($score) {
            case SiteAuditCheckBase::AUDIT_CHECK_SCORE_PASS:
                return "✅ Pass";
            case SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN:
                return "⚠️ Warning";
            case SiteAuditCheckBase::AUDIT_CHECK_SCORE_FAIL:
                return "❌ Fail";
            case SiteAuditCheckBase::AUDIT_CHECK_SCORE_INFO:
                return "ℹ️ Info";
            default:
                return "Unknown";
        }
    }

    // Add these new protected methods to get the required information
    protected function getEnvironment()
    {
        // You may need to adjust this based on how you determine the environment
        return getenv('AH_SITE_ENVIRONMENT') ?: 'Unknown';
    }

    protected function getDrushAlias()
    {
        // For Drush 8 (Drupal 7)
        if (function_exists('drush_get_context')) {
            return drush_get_context('DRUSH_ALIAS') ?: 'None';
        }
        
        // For Drush 9 and later
        if (class_exists('\Drush\Drush')) {
            $aliasManager = \Drush\Drush::aliasManager();
            $selfAlias = $aliasManager->getSelf();
            return $selfAlias->name();
        }
        
        // Fallback for cases where we can't determine the alias
        return 'None';
    }

    protected function getSiteUri()
    {
        // For Drupal 7
        if (function_exists('variable_get')) {
            $base_url = variable_get('site_url', '');
            if ($base_url) {
                return $base_url;
            }
        }

        // For Drupal 8+
        if (class_exists('\Drupal')) {
            return \Drupal::request()->getSchemeAndHttpHost() . \Drupal::request()->getBaseUrl();
        }

        // Fallback
        return 'Unknown';
    }

    protected function extractDatabaseSizeInfo($result)
    {
        foreach ($result['reports'] as $report) {
            if (isset($report['checks'])) {
                foreach ($report['checks'] as $checkKey => $check) {
                    if (strpos($checkKey, 'DatabaseSize') !== false) {
                        $info = [];
                        $resultLines = explode('<br>', $check['result']);
                        foreach ($resultLines as $line) {
                            $parts = explode(': ', $line, 2);
                            if (count($parts) == 2) {
                                $info[trim($parts[0])] = trim($parts[1]);
                            }
                        }
                        $info['score'] = $check['score'];
                        $info['action'] = $check['action'] ?? null;
                        return $info;
                    }
                }
            }
        }
        return null;
    }

    protected function generateHtmlReport($result)
    {
        $html = "<html><head><title>Site Audit Report</title></head><body>";
        $html .= "<h1>Site Audit Report</h1>";
        $html .= "<p>Generated on: " . date('Y-m-d H:i:s', $result['time']) . "</p>";

        // Add environment information
        $html .= "<h2>Environment Information</h2>";
        $html .= "<table border='1'><tr><th>Key</th><th>Value</th></tr>";
        $html .= "<tr><td>Environment</td><td>" . $this->getEnvironment() . "</td></tr>";
        $html .= "<tr><td>Site URI</td><td>" . $this->getSiteUri() . "</td></tr>";
        $html .= "</table>";

        // Add database size information
        if (isset($result['database_size_info'])) {
            $html .= "<h2>Database Size Information</h2>";
            $html .= "<table border='1'><tr><th>Metric</th><th>Value</th></tr>";
            foreach ($result['database_size_info'] as $key => $value) {
                if ($key !== 'score' && $key !== 'action') {
                    $html .= "<tr><td>$key</td><td>$value</td></tr>";
                }
            }
            $html .= "</table>";

            if ($result['database_size_info']['score'] == SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN) {
                $html .= "<p><strong>Warning:</strong> High cache table usage detected.</p>";
            }

            if (!empty($result['database_size_info']['action'])) {
                $html .= "<p><strong>Recommended Action:</strong> " . $result['database_size_info']['action'] . "</p>";
            }
        }

        // Add other reports
        foreach ($result['reports'] as $reportKey => $report) {
            $html .= "<h2>" . $report['label'] . "</h2>";
            $html .= "<p>Overall Score: " . $report['percent'] . "%</p>";

            foreach ($report['checks'] as $checkKey => $check) {
                if (strpos($checkKey, 'DatabaseSize') === false) {  // Skip DatabaseSize as it's already included
                    $html .= "<h3>" . $check['label'] . "</h3>";
                    $html .= "<p><strong>Result:</strong> " . $check['result'] . "</p>";
                    
                    if (!empty($check['action'])) {
                        $html .= "<p><strong>Action:</strong> " . $check['action'] . "</p>";
                    }

                    if (!empty($check['description'])) {
                        $html .= "<p><strong>Description:</strong> " . $check['description'] . "</p>";
                    }

                    $html .= "<p><strong>Score:</strong> " . $this->getScoreEmoji($check['score']) . "</p>";
                }
            }
        }

        $html .= "</body></html>";
        return $html;
    }
}