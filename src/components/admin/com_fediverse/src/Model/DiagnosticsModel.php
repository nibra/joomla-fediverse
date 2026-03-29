<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * DiagnosticsModel Class
 *
 * Build a diagnostics checklist for Fediverse operator readiness.
 *
 * @since  __DEPLOY_VERSION__
 */
final class DiagnosticsModel extends BaseModel
{
    /**
     * Required Fediverse plugins for diagnostics readiness.
     *
     * @var list<array{folder:string,element:string}>
     *
     * @since  __DEPLOY_VERSION__
     */
    private const REQUIRED_CORE_PLUGINS = [
        ['folder' => 'system',  'element' => 'fediverse'],
        ['folder' => 'task',    'element' => 'fediverse'],
        ['folder' => 'content', 'element' => 'fediverse'],
        ['folder' => 'content', 'element' => 'fediverse_tags'],
        ['folder' => 'user',    'element' => 'fediverse'],
    ];

    /**
     * Required scheduler task defaults for diagnostics readiness.
     *
     * @var array<string,array<string,mixed>>
     *
     * @since  __DEPLOY_VERSION__
     */
    private const REQUIRED_SCHEDULER_TASKS = [
        'fediverse.inbox_worker' => [
            'title'           => 'Process inbound ActivityPub inbox items',
            'defaultRuleType' => 'interval-minutes',
            'defaultInterval' => 3,
        ],
        'fediverse.delivery_worker' => [
            'title'           => 'Deliver queued ActivityPub activities',
            'defaultRuleType' => 'interval-minutes',
            'defaultInterval' => 3,
        ],
        'fediverse.key_rotation' => [
            'title'           => 'Fediverse Key Rotation',
            'defaultRuleType' => 'manual',
            'defaultInterval' => 1440,
        ],
        'fediverse.cleanup' => [
            'title'           => 'Fediverse Cleanup',
            'defaultRuleType' => 'interval-minutes',
            'defaultInterval' => 1440,
        ],
    ];

    /**
     * @var callable
     */
    private $paramsProvider;

    /**
     * Initialize the diagnostics model.
     *
     * @params DatabaseInterface $db Database connection.
     * @params array<string,mixed> $config Model configuration.
     * @params null|callable():array<string,mixed> $paramsProvider Optional component params provider.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private DatabaseInterface $db,
        array $config = [],
        ?callable $paramsProvider = null,
    ) {
        parent::__construct($config);
        $this->paramsProvider = $paramsProvider ?? static fn(): array => ComponentHelper::getParams('com_fediverse')->toArray();
    }

    /**
     * Build diagnostics checks for the admin wizard.
     *
     * @return  array<int,array<string,string>>  Ordered diagnostics checks.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getChecks(): array
    {
        return [
            $this->checkPublicBaseUrl(),
            $this->checkCorePlugins(),
            $this->checkSchedulerTasks(),
            $this->checkLocalActors(),
        ];
    }

    /**
     * Return the configured public base URL.
     *
     * @return  string  Public base URL or empty string.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getPublicBaseUrl(): string
    {
        try {
            /** @var array<string,mixed> $params */
            $params = ($this->paramsProvider)();
            $base   = trim((string) ($params['public_base_url'] ?? ''));
        } catch (\Throwable) {
            $base = '';
        }

        return $base;
    }

    /**
     * Persist a new public base URL into component params.
     *
     * @param   string  $publicBaseUrl  Public base URL value.
     *
     * @return  bool  True when the value was saved.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setPublicBaseUrl(string $publicBaseUrl): bool
    {
        $base = trim($publicBaseUrl);

        if ($base === '' || !preg_match('#^https?://#i', $base)) {
            return false;
        }

        try {
            $selectQuery = $this->db->createQuery()
                ->select($this->db->quoteName('params'))
                ->from($this->db->quoteName('#__extensions'))
                ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('component'))
                ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('com_fediverse'))
                ->setLimit(1);
            $this->db->setQuery($selectQuery);
            $paramsRaw = $this->db->loadResult();

            $params = [];

            if (is_string($paramsRaw) && trim($paramsRaw) !== '') {
                $decoded = json_decode($paramsRaw, true);
                if (is_array($decoded)) {
                    $params = $decoded;
                }
            }

            $params['public_base_url'] = $base;
            $encodedParams = json_encode($params, JSON_UNESCAPED_SLASHES);

            if (!is_string($encodedParams) || $encodedParams === '') {
                return false;
            }

            $encoded = $encodedParams;
            $updateQuery = $this->db->createQuery()
                ->update($this->db->quoteName('#__extensions'))
                ->set($this->db->quoteName('params') . ' = :params')
                ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('component'))
                ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('com_fediverse'))
                ->bind(':params', $encoded, ParameterType::STRING);
            $this->db->setQuery($updateQuery);
            $this->db->execute();
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /**
     * Return extension ids for required Fediverse plugins.
     *
     * @return  list<int>  Extension ids for core Fediverse plugins.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getCoreFediversePluginIds(): array
    {
        return $this->getCoreFediversePluginIdsByEnabledState(null);
    }

    /**
     * Return extension ids for required Fediverse plugins that are disabled.
     *
     * @return  list<int>  Extension ids for disabled core Fediverse plugins.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getDisabledCoreFediversePluginIds(): array
    {
        return $this->getCoreFediversePluginIdsByEnabledState(false);
    }

    /**
     * Return extension ids for required Fediverse plugins filtered by enabled state.
     *
     * @param   ?bool  $enabled  Enabled-state filter. Null returns all matches.
     *
     * @return  list<int>  Extension ids for matching core Fediverse plugins.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getCoreFediversePluginIdsByEnabledState(?bool $enabled): array
    {
        try {
            $query = 'SELECT ' . $this->db->quoteName('extension_id')
                . ' FROM ' . $this->db->quoteName('#__extensions')
                . ' WHERE ' . $this->db->quoteName('type') . ' = ' . $this->db->quote('plugin')
                . ' AND (' . $this->buildRequiredCorePluginWhereClause() . ')';

            if ($enabled !== null) {
                $query .= ' AND ' . $this->db->quoteName('enabled') . ' = ' . ($enabled ? '1' : '0');
            }

            $this->db->setQuery($query);
            $ids = $this->db->loadColumn();
        } catch (\Throwable) {
            $ids = [];
        }

        $ids = array_map('intval', is_array($ids) ? $ids : []);
        $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));

        return $ids;
    }

    /**
     * Return editable scheduler settings for all required Fediverse tasks.
     *
     * @return  array<string,array<string,mixed>>  Task settings keyed by task type.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getRequiredSchedulerTaskSettings(): array
    {
        $defaults = $this->buildDefaultSchedulerTaskSettings();

        try {
            $requiredTaskTypes = array_keys(self::REQUIRED_SCHEDULER_TASKS);
            $query = 'SELECT '
                . $this->db->quoteName('id') . ', '
                . $this->db->quoteName('type') . ', '
                . $this->db->quoteName('title') . ', '
                . $this->db->quoteName('state') . ', '
                . $this->db->quoteName('execution_rules') . ', '
                . $this->db->quoteName('params')
                . ' FROM ' . $this->db->quoteName('#__scheduler_tasks')
                . ' WHERE ' . $this->db->quoteName('type') . ' IN ('
                . $this->db->quote($requiredTaskTypes[0]) . ', '
                . $this->db->quote($requiredTaskTypes[1]) . ', '
                . $this->db->quote($requiredTaskTypes[2]) . ', '
                . $this->db->quote($requiredTaskTypes[3])
                . ')';

            $this->db->setQuery($query);
            $rows = $this->db->loadAssocList();
        } catch (\Throwable) {
            $rows = [];
        }

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = trim((string) ($row['type'] ?? ''));
            if (!isset($defaults[$type])) {
                continue;
            }

            $task                  = $defaults[$type];
            $task['id']            = max(0, (int) ($row['id'] ?? 0));
            $task['exists']        = $task['id'] > 0;
            $task['title']         = trim((string) ($row['title'] ?? '')) !== '' ? trim((string) $row['title']) : (string) $task['title'];
            $task['enabled']       = (int) (($row['state'] ?? 0) === 1 || (string) ($row['state'] ?? '') === '1');
            $task['executionRules'] = $this->parseSchedulerExecutionRules(
                is_string($row['execution_rules'] ?? null) ? (string) $row['execution_rules'] : ''
            );
            $task['params'] = $this->parseJsonObject(
                is_string($row['params'] ?? null) ? (string) $row['params'] : ''
            );

            $ruleType = (string) ($task['executionRules']['rule-type'] ?? $task['ruleType']);
            if ($ruleType !== 'manual' && $ruleType !== 'interval-minutes') {
                $ruleType = (string) $task['ruleType'];
            }

            $interval = (int) ($task['executionRules']['interval-minutes'] ?? $task['intervalMinutes']);
            if ($interval < 1) {
                $interval = (int) $task['intervalMinutes'];
            }

            $execDay = (string) ($task['executionRules']['exec-day'] ?? $task['execDay']);
            if (!preg_match('/^\d{1,2}$/', $execDay)) {
                $execDay = (string) $task['execDay'];
            }

            $execTime = (string) ($task['executionRules']['exec-time'] ?? $task['execTime']);
            if (preg_match('/^\d{2}:\d{2}/', $execTime, $matches) === 1) {
                $execTime = $matches[0];
            } else {
                $execTime = (string) $task['execTime'];
            }

            $task['ruleType']        = $ruleType;
            $task['intervalMinutes'] = $interval;
            $task['execDay']         = $execDay;
            $task['execTime']        = $execTime;

            $defaults[$type] = $task;
        }

        return $defaults;
    }

    /**
     * Check that a valid public base URL is configured.
     *
     * @return  array<string,string>  Check result.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function checkPublicBaseUrl(): array
    {
        $base = $this->getPublicBaseUrl();

        if ($base === '' || !preg_match('#^https?://#i', $base)) {
            return $this->buildCheck(
                'public_base_url',
                'fail',
                'Public base URL is missing or invalid.',
                'Remote servers cannot discover this Joomla site yet.',
                'Save the public HTTPS address that remote servers can reach for this site.'
            );
        }

        return $this->buildCheck(
            'public_base_url',
            'pass',
            'Public base URL is configured.',
            'Discovery and actor links can point remote servers to the right site address.',
            'Keep this URL aligned with the externally reachable HTTPS address if the site domain changes.'
        );
    }

    /**
     * Check that core Fediverse plugins are enabled.
     *
     * @return  array<string,string>  Check result.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function checkCorePlugins(): array
    {
        try {
            $query = 'SELECT COUNT(*)'
                . ' FROM ' . $this->db->quoteName('#__extensions')
                . ' WHERE ' . $this->db->quoteName('type') . ' = ' . $this->db->quote('plugin')
                . ' AND ' . $this->db->quoteName('enabled') . ' = 1'
                . ' AND (' . $this->buildRequiredCorePluginWhereClause() . ')';

            $this->db->setQuery($query);
            $enabled = (int) $this->db->loadResult();
        } catch (\Throwable) {
            $enabled = 0;
        }

        if ($enabled < count(self::REQUIRED_CORE_PLUGINS)) {
            return $this->buildCheck(
                'plugins',
                'fail',
                'One or more required Fediverse plugins are disabled.',
                'Article publishing and actor provisioning hooks will not run reliably.',
                'Enable the required Fediverse plugins from the dashboard action or the Joomla plugins list.'
            );
        }

        return $this->buildCheck(
            'plugins',
            'pass',
            'Core Fediverse plugins are enabled.',
            'Publishing, actor provisioning, shortcode handling, and background task integration are available.',
            'If behavior still looks wrong, continue with the scheduler and actor checks before troubleshooting deliveries.'
        );
    }

    /**
     * Build SQL where clause for required core plugin identity pairs.
     *
     * @return  string  SQL fragment combining required folder/element pairs.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildRequiredCorePluginWhereClause(): string
    {
        $parts = [];

        foreach (self::REQUIRED_CORE_PLUGINS as $plugin) {
            $parts[] = '('
                . $this->db->quoteName('folder') . ' = ' . $this->db->quote($plugin['folder'])
                . ' AND '
                . $this->db->quoteName('element') . ' = ' . $this->db->quote($plugin['element'])
                . ')';
        }

        return implode(' OR ', $parts);
    }

    /**
     * Check that Fediverse scheduler tasks are available.
     *
     * @return  array<string,string>  Check result.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function checkSchedulerTasks(): array
    {
        $registered = 0;
        foreach ($this->getRequiredSchedulerTaskSettings() as $taskSettings) {
            if ((bool) ($taskSettings['exists'] ?? false)) {
                $registered++;
            }
        }

        if ($registered < 4) {
            return $this->buildCheck(
                'scheduler',
                'warn',
                'One or more Fediverse scheduler tasks are missing or not registered yet.',
                'Publishing may queue work that never gets processed, and maintenance tasks may be skipped.',
                'Open the scheduler task dialog, confirm all Fediverse tasks exist, and save the configuration to register defaults where needed.'
            );
        }

        return $this->buildCheck(
            'scheduler',
            'pass',
            'Fediverse scheduler tasks are registered.',
            'Background delivery, inbox processing, cleanup, and key rotation can run on schedule.',
            'Review the run mode and interval if outbound deliveries still move too slowly for your site.'
        );
    }

    /**
     * Build default scheduler task settings keyed by task type.
     *
     * @return  array<string,array<string,mixed>>  Default scheduler settings.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildDefaultSchedulerTaskSettings(): array
    {
        $defaults = [];

        foreach (self::REQUIRED_SCHEDULER_TASKS as $type => $taskDefaults) {
            $defaults[$type] = [
                'id'              => 0,
                'type'            => $type,
                'title'           => (string) ($taskDefaults['title'] ?? $type),
                'exists'          => false,
                'enabled'         => 1,
                'ruleType'        => (string) ($taskDefaults['defaultRuleType'] ?? 'interval-minutes'),
                'intervalMinutes' => (int) ($taskDefaults['defaultInterval'] ?? 5),
                'execDay'         => gmdate('d'),
                'execTime'        => gmdate('H:i'),
                'executionRules'  => [],
                'params'          => [],
            ];
        }

        return $defaults;
    }

    /**
     * Parse scheduler execution-rules JSON into an array map.
     *
     * @param   string  $json  Execution-rules JSON payload.
     *
     * @return  array<string,mixed>  Parsed execution rules.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function parseSchedulerExecutionRules(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Parse JSON object text into an associative array.
     *
     * @param   string  $json  JSON object text.
     *
     * @return  array<string,mixed>  Parsed object map.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function parseJsonObject(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Check whether at least one local actor exists.
     *
     * @return  array<string,string>  Check result.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function checkLocalActors(): array
    {
        try {
            $query = 'SELECT COUNT(*)'
                . ' FROM ' . $this->db->quoteName('#__fediverse_actors')
                . ' WHERE ' . $this->db->quoteName('type') . ' = ' . $this->db->quote('local')
                . ' AND ' . $this->db->quoteName('is_enabled') . ' = 1';

            $this->db->setQuery($query);
            $count = (int) $this->db->loadResult();
        } catch (\Throwable) {
            $count = 0;
        }

        if ($count < 1) {
            return $this->buildCheck(
                'actors',
                'warn',
                'No enabled local actor is available yet.',
                'Authors cannot publish to the Fediverse until at least one local actor exists and is enabled.',
                'Open the Actors view, create or enable a local actor, then publish a test article to confirm the author mapping works.'
            );
        }

        return $this->buildCheck(
            'actors',
            'pass',
            'At least one local actor is enabled.',
            'The site has a publishing identity ready for Fediverse delivery.',
            'If a specific article still does not publish, verify that its Joomla author is mapped to the expected local actor.'
        );
    }

    /**
     * Build a diagnostics check row with summary, impact, and remediation text.
     *
     * @params string $id Check identifier.
     * @params string $status Check status.
     * @params string $message Short summary.
     * @params string $impact Why this matters operationally.
     * @params string $remediation Practical next step.
     *
     * @return  array<string,string>  Diagnostics row.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildCheck(
        string $id,
        string $status,
        string $message,
        string $impact,
        string $remediation
    ): array {
        return [
            'id' => $id,
            'status' => $status,
            'message' => $message,
            'impact' => $impact,
            'remediation' => $remediation,
        ];
    }
}
