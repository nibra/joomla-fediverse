<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\View\Policy;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use NX\Component\Fediverse\Administrator\Model\PoliciesModel;
use NX\Component\Fediverse\Administrator\Service\License\LicenseService;

/**
 * HtmlView Class
 *
 * Render the Fediverse policy edit view.
 *
 * @since  __DEPLOY_VERSION__
 */
final class HtmlView extends BaseHtmlView
{
    /**
     * Policy row being edited.
     *
     * @var array<string, mixed>
     *
     * @since  __DEPLOY_VERSION__
     */
    public array $item = [];

    /**
     * Whether the current item is new.
     *
     * @var bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public bool $isNew = true;

    /**
     * Whether the installation has an active Pro license.
     *
     * @var bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public bool $isPro = false;

    /**
     * Whether the configured Pro license has expired.
     *
     * @var bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public bool $licenseExpired = false;

    /**
     * Display the policy edit view.
     *
     * @param   string|null  $tpl  Template name.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function display($tpl = null): void
    {
        $app    = Factory::getApplication();
        $domain = strtolower(trim($app->input->getString('domain', '')));

        $license = Factory::getContainer()->get(LicenseService::class);
        $this->isPro = $license->isPro() && !$license->isExpired();
        $this->licenseExpired = $license->isExpired();

        $this->item = [
            'domain' => $domain,
            'policy' => 'block',
            'reason' => '',
        ];

        $model = $this->getPoliciesModel();
        $policy = $domain !== '' ? $model->findByDomain($domain) : null;

        if ($policy !== null) {
            $this->item = [
                'domain' => $policy->domain,
                'policy' => $policy->policy,
                'reason' => $policy->reason,
            ];
            $this->isNew = false;
        }

        $this->addToolbar();

        if ($this->getLayout() === 'default') {
            $this->setLayout('edit');
        }

        parent::display($tpl);
    }

    /**
     * Add the policy edit toolbar.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function addToolbar(): void
    {
        ToolbarHelper::title(
            Text::_($this->isNew ? 'COM_FEDIVERSE_POLICIES_NEW_TITLE' : 'COM_FEDIVERSE_POLICIES_EDIT_TITLE'),
            'shield'
        );

        if ($this->isPro) {
            ToolbarHelper::save('policies.save');
            ToolbarHelper::save2new('policies.save2new');
        }

        ToolbarHelper::cancel('policies.close', 'JTOOLBAR_CLOSE');
    }

    /**
     * Load the policies model.
     *
     * @return  PoliciesModel
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getPoliciesModel(): PoliciesModel
    {
        /** @var PoliciesModel|false $model */
        $model = $this->getModel('Policies');

        if (!$model instanceof PoliciesModel) {
            throw new \RuntimeException('Unable to load policies model.');
        }

        return $model;
    }
}
