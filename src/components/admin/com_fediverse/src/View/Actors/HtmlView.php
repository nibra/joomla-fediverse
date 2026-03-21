<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\View\Actors;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use NX\Component\Fediverse\Administrator\Service\License\LicenseService;

/**
 * HtmlView Class
 *
 * Render the Fediverse actors list view.
 *
 * @since  __DEPLOY_VERSION__
 */
final class HtmlView extends BaseHtmlView
{
    /**
     * List of actor rows.
     *
     * @var array<int, array<string, mixed>>
     *
     * @since  __DEPLOY_VERSION__
     */
    public array $items = [];

    /**
     * Maximum actors allowed by the current license tier.
     *
     * @var int
     *
     * @since  __DEPLOY_VERSION__
     */
    public int $actorLimit = 1;

    /**
     * Whether the installation has an active Pro license.
     *
     * @var bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public bool $isPro = false;

    /**
     * Display the actors list view.
     *
     * @params string $tpl Template name.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function display($tpl = null): void
    {
        $license          = Factory::getContainer()->get(LicenseService::class);
        $this->isPro       = $license->isPro() && !$license->isExpired();
        $this->actorLimit  = $license->getTier()->actorLimit();

        /** @var \NX\Component\Fediverse\Administrator\Model\ActorsModel $model */
        $model       = $this->getModel();
        $this->items = $model->getList('local');

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the actors toolbar.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function addToolbar(): void
    {
        $user    = $this->getCurrentUser();
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_FEDIVERSE_ACTORS_TITLE'), 'users');

        if (
            $user->authorise('core.admin', 'com_fediverse')
            || $user->authorise('core.options', 'com_fediverse')
        ) {
            $toolbar->preferences('com_fediverse');
        }
    }
}
