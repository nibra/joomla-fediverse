<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\View\Actor;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;

/**
 * HtmlView Class
 *
 * Render the Fediverse actor edit view.
 *
 * @since  __DEPLOY_VERSION__
 */
final class HtmlView extends BaseHtmlView
{
    /**
     * Actor instance being edited.
     *
     * @var Actor
     *
     * @since  __DEPLOY_VERSION__
     */
    public Actor $actor;

    /**
     * Display the actor edit view.
     *
     * @params string $tpl Template name.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function display($tpl = null): void
    {
        $app     = Factory::getApplication();
        $actorId = $app->input->getInt('id', 0);

        /** @var \NX\Component\Fediverse\Administrator\Model\ActorsModel $model */
        $model = $this->getModel();
        $actor = $model->getById($actorId);

        if ($actor === null || !$actor->isLocal()) {
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_ACTOR_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_fediverse&view=actors', false));

            return;
        }

        $this->actor = $actor;

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the actor edit toolbar.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_FEDIVERSE_ACTORS_EDIT_HEADING'), 'users');
    }
}
