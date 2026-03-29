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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use NX\Component\Fediverse\Administrator\Event\ConfigurationExportedEvent;
use NX\Component\Fediverse\Administrator\Event\ConfigurationImportedEvent;
use NX\Component\Fediverse\Administrator\Service\Administration\ConfigurationTransferService;
use NX\Component\Fediverse\Administrator\Service\Events\FediverseDomainEventDispatcherInterface;
use NX\Component\Fediverse\Administrator\Service\License\LicenseTier;
use NX\Component\Fediverse\Administrator\Service\License\LicenseService;

/**
 * DashboardController Class
 *
 * Handle Dashboard requests.
 *
 * @since  __DEPLOY_VERSION__
 */
final class DashboardController extends BaseController
{
    /**
     * Export the Fediverse configuration package.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function exportConfigPackage(): void
    {
        $app = $this->app;

        if (!Session::checkToken('request')) {
            $this->setRedirect(Route::_('index.php?option=com_fediverse&view=dashboard', false), Text::_('JINVALID_TOKEN'), 'error');

            return;
        }

        try {
            Factory::getContainer()->get(LicenseService::class)->requireTier(LicenseTier::Pro);

            $json = Factory::getContainer()->get(ConfigurationTransferService::class)->exportPackage();
            Factory::getContainer()->get(FediverseDomainEventDispatcherInterface::class)->dispatch(
                new ConfigurationExportedEvent(
                    (int) $app->getIdentity()->id
                )
            );

            $filename = 'fediverse-configuration-' . gmdate('Ymd-His') . '.json';
            $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
            $app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true);
            $app->sendHeaders();
            echo $json;
            $app->close();
        } catch (\Throwable $e) {
            $this->setRedirect(
                Route::_('index.php?option=com_fediverse&view=dashboard', false),
                $e->getMessage(),
                'warning'
            );
        }
    }

    /**
     * Import the Fediverse configuration package.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function importConfigPackage(): void
    {
        $app = $this->app;

        if (!Session::checkToken('request')) {
            $this->setRedirect(Route::_('index.php?option=com_fediverse&view=dashboard', false), Text::_('JINVALID_TOKEN'), 'error');

            return;
        }

        try {
            Factory::getContainer()->get(LicenseService::class)->requireTier(LicenseTier::Pro);

            if ((int) $app->input->post->getInt('confirm_replace', 0) !== 1) {
                throw new \RuntimeException(Text::_('COM_FEDIVERSE_TRANSFER_CONFIRM_REQUIRED'));
            }

            $upload = (array) $app->input->files->get('transfer_package', [], 'array');
            $tmpName = (string) ($upload['tmp_name'] ?? '');

            if ($tmpName === '' || !is_file($tmpName)) {
                throw new \RuntimeException(Text::_('COM_FEDIVERSE_TRANSFER_IMPORT_FILE_REQUIRED'));
            }

            $json = file_get_contents($tmpName);

            if (!is_string($json) || $json === '') {
                throw new \RuntimeException(Text::_('COM_FEDIVERSE_TRANSFER_IMPORT_INVALID'));
            }

            $summary = Factory::getContainer()->get(ConfigurationTransferService::class)->importPackage($json);
            Factory::getContainer()->get(FediverseDomainEventDispatcherInterface::class)->dispatch(
                new ConfigurationImportedEvent(
                    (int) ($summary['scheduler_tasks'] ?? 0),
                    (int) ($summary['user_settings'] ?? 0),
                    (int) ($summary['content_settings'] ?? 0),
                    (int) ($summary['domain_policies'] ?? 0),
                    (int) ($summary['oauth_clients'] ?? 0),
                    (int) $app->getIdentity()->id
                )
            );

            $this->setRedirect(
                Route::_('index.php?option=com_fediverse&view=dashboard', false),
                Text::_('COM_FEDIVERSE_TRANSFER_IMPORT_SUCCESS'),
                'message'
            );
        } catch (\Throwable $e) {
            $this->setRedirect(
                Route::_('index.php?option=com_fediverse&view=dashboard', false),
                $e->getMessage(),
                'warning'
            );
        }
    }
}
