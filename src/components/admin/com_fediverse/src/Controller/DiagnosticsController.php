<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use NX\Component\Fediverse\Administrator\Model\DiagnosticsModel;

/**
 * DiagnosticsController Class
 *
 * Handle Diagnostics requests.
 *
 * @since  __DEPLOY_VERSION__
 */
final class DiagnosticsController extends BaseController
{
    /**
     * Display the diagnostics wizard view.
     *
     * @param   bool   $cachable   Whether the view can be cached.
     * @param   array  $urlparams  URL parameters safe-list.
     *
     * @return  static
     *
     * @since  __DEPLOY_VERSION__
     */
    public function display($cachable = false, $urlparams = []): static
    {
        return parent::display($cachable, $urlparams);
    }

    /**
     * Save the public base URL from the diagnostics modal.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setPublicBaseUrl(): void
    {
        $app  = $this->app;
        $user = $app->getIdentity();

        if (!Session::checkToken('request')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect('index.php?option=com_fediverse&view=dashboard');

            return;
        }

        if (!$user->authorise('core.manage', 'com_fediverse')) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect('index.php?option=com_fediverse&view=dashboard');

            return;
        }

        $publicBaseUrl = trim($app->input->post->getString('public_base_url', ''));
        $model         = $this->getDiagnosticsModel();

        if ($model->setPublicBaseUrl($publicBaseUrl)) {
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_DIAGNOSTICS_PUBLIC_BASE_URL_SAVED'), 'message');
        } else {
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_DIAGNOSTICS_PUBLIC_BASE_URL_SAVE_FAILED'), 'error');
        }

        $app->redirect('index.php?option=com_fediverse&view=dashboard');
    }

    /**
     * Enable all required Fediverse plugins directly from diagnostics.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function activateCorePlugins(): void
    {
        $app  = $this->app;
        $user = $app->getIdentity();

        if (!Session::checkToken()) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect('index.php?option=com_fediverse&view=dashboard');

            return;
        }

        if (!$user->authorise('core.manage', 'com_fediverse')) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect('index.php?option=com_fediverse&view=dashboard');

            return;
        }

        try {
            $diagnosticsModel = $this->getDiagnosticsModel();
            $pluginIds        = $diagnosticsModel->getCoreFediversePluginIds();
            $disabledPluginIds = $diagnosticsModel->getDisabledCoreFediversePluginIds();

            if ($pluginIds === []) {
                $app->enqueueMessage(Text::_('COM_FEDIVERSE_DIAGNOSTICS_PLUGINS_MISSING'), 'error');
                $app->redirect('index.php?option=com_fediverse&view=dashboard');

                return;
            }

            if ($disabledPluginIds !== []) {
                $pluginModel = $this->getJoomlaPluginModel();
                $idsToEnable = $disabledPluginIds;
                $saved       = (bool) $pluginModel->publish($idsToEnable, 1);

                if (!$saved) {
                    $app->enqueueMessage(Text::_('COM_FEDIVERSE_DIAGNOSTICS_PLUGINS_ACTIVATE_FAILED'), 'error');
                    $app->redirect('index.php?option=com_fediverse&view=dashboard');

                    return;
                }
            }

            $app->enqueueMessage(
                Text::sprintf('COM_FEDIVERSE_DIAGNOSTICS_PLUGINS_ACTIVATED', count($disabledPluginIds)),
                'message'
            );
        } catch (\Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $app->redirect('index.php?option=com_fediverse&view=dashboard');
    }

    /**
     * Save scheduler settings for required Fediverse tasks.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function saveSchedulerTasks(): void
    {
        $app  = $this->app;
        $user = $app->getIdentity();

        if (!Session::checkToken()) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect('index.php?option=com_fediverse&view=dashboard');

            return;
        }

        if (!$user->authorise('core.manage', 'com_fediverse')) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect('index.php?option=com_fediverse&view=dashboard');

            return;
        }

        $submittedTasks = $app->input->post->get('tasks', [], 'array');
        if (!is_array($submittedTasks)) {
            $submittedTasks = [];
        }

        try {
            $diagnosticsModel = $this->getDiagnosticsModel();
            $taskSettings     = $diagnosticsModel->getRequiredSchedulerTaskSettings();
            $schedulerModel   = $this->getJoomlaSchedulerTaskModel();
            $savedCount       = 0;

            foreach ($taskSettings as $taskType => $currentSettings) {
                $taskInput = $submittedTasks[$taskType] ?? [];
                if (!is_array($taskInput)) {
                    $taskInput = [];
                }

                if (!$this->saveSchedulerTask($schedulerModel, $currentSettings, $taskInput)) {
                    $app->enqueueMessage(Text::_('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_SAVE_FAILED'), 'error');
                    $app->redirect('index.php?option=com_fediverse&view=dashboard');

                    return;
                }

                $savedCount++;
            }

            $app->enqueueMessage(
                Text::sprintf('COM_FEDIVERSE_DIAGNOSTICS_SCHEDULER_SAVED', $savedCount),
                'message'
            );
        } catch (\Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $app->redirect('index.php?option=com_fediverse&view=dashboard');
    }

    /**
     * Load diagnostics model for write actions.
     *
     * @return  DiagnosticsModel
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getDiagnosticsModel(): DiagnosticsModel
    {
        /** @var DiagnosticsModel|false $model */
        $model = $this->getModel('Diagnostics', 'Administrator', ['ignore_request' => true]);

        if (!$model instanceof DiagnosticsModel) {
            throw new \RuntimeException('Unable to load diagnostics model.');
        }

        return $model;
    }

    /**
     * Load the Joomla core plugin model.
     *
     * @return  object  Plugin model exposing publish().
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getJoomlaPluginModel(): object
    {
        $model = Factory::getApplication()
            ->bootComponent('com_plugins')
            ->getMVCFactory()
            ->createModel('Plugin', 'Administrator', ['ignore_request' => true]);

        if (!is_object($model) || !method_exists($model, 'publish')) {
            throw new \RuntimeException('Unable to load Joomla plugin model.');
        }

        return $model;
    }

    /**
     * Load the Joomla core scheduler task model.
     *
     * @return  object  Scheduler task model exposing save().
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getJoomlaSchedulerTaskModel(): object
    {
        $model = Factory::getApplication()
            ->bootComponent('com_scheduler')
            ->getMVCFactory()
            ->createModel('Task', 'Administrator', ['ignore_request' => true]);

        if (!is_object($model) || !method_exists($model, 'save')) {
            throw new \RuntimeException('Unable to load Joomla scheduler task model.');
        }

        return $model;
    }

    /**
     * Save one scheduler task from submitted diagnostics modal input.
     *
     * @param   object              $schedulerModel  Scheduler task model.
     * @param   array<string,mixed> $currentSettings Current task settings.
     * @param   array<string,mixed> $taskInput       Submitted task values.
     *
     * @return  bool  True when task save succeeded.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function saveSchedulerTask(object $schedulerModel, array $currentSettings, array $taskInput): bool
    {
        $ruleType = (string) ($taskInput['rule_type'] ?? ($currentSettings['ruleType'] ?? 'interval-minutes'));
        if ($ruleType !== 'manual' && $ruleType !== 'interval-minutes') {
            $ruleType = 'interval-minutes';
        }

        $interval = (int) ($taskInput['interval_minutes'] ?? ($currentSettings['intervalMinutes'] ?? 5));
        if ($interval < 1) {
            $interval = 1;
        }
        if ($interval > 10080) {
            $interval = 10080;
        }

        $execDay = (string) ($currentSettings['execDay'] ?? gmdate('d'));
        if (!preg_match('/^\d{1,2}$/', $execDay)) {
            $execDay = gmdate('d');
        }

        $execTime = (string) ($currentSettings['execTime'] ?? gmdate('H:i'));
        if (preg_match('/^\d{2}:\d{2}/', $execTime, $matches) === 1) {
            $execTime = $matches[0];
        } else {
            $execTime = gmdate('H:i');
        }

        $enabled = (int) (
            array_key_exists('enabled', $taskInput)
            && (string) ($taskInput['enabled'] ?? '') === '1'
        );

        $executionRules = [
            'rule-type' => $ruleType,
            'exec-day'  => $execDay,
            'exec-time' => $execTime,
        ];
        if ($ruleType === 'interval-minutes') {
            $executionRules['interval-minutes'] = (string) $interval;
        }

        $params = $currentSettings['params'] ?? [];
        if (!is_array($params)) {
            $params = [];
        }

        $taskData = [
            'id'              => (int) ($currentSettings['id'] ?? 0),
            'title'           => (string) ($currentSettings['title'] ?? ''),
            'type'            => (string) ($currentSettings['type'] ?? ''),
            'state'           => $enabled,
            'execution_rules' => $executionRules,
            'params'          => $params,
        ];

        return (bool) $schedulerModel->save($taskData);
    }
}
