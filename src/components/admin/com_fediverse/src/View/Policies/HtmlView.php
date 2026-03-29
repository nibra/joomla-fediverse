<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\View\Policies;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;
use NX\Component\Fediverse\Administrator\Service\License\LicenseService;

/**
 * HtmlView Class
 *
 * Render the Fediverse domain policies view.
 *
 * @since  __DEPLOY_VERSION__
 */
final class HtmlView extends BaseHtmlView
{
    /**
     * List of domain policy rows.
     *
     * @var array<int, array<string, mixed>>
     *
     * @since  __DEPLOY_VERSION__
     */
    public array $items = [];

    /**
     * Whether the installation has an active Pro license.
     *
     * @var bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public bool $isPro = false;

    /**
     * Whether the Pro license has expired.
     *
     * @var bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public bool $licenseExpired = false;

    /**
     * Pagination object for the list.
     *
     * @var Pagination
     *
     * @since  __DEPLOY_VERSION__
     */
    public Pagination $pagination;

    /**
     * Active search filter.
     *
     * @var string
     *
     * @since  __DEPLOY_VERSION__
     */
    public string $filterSearch = '';

    /**
     * Active policy filter.
     *
     * @var string
     *
     * @since  __DEPLOY_VERSION__
     */
    public string $filterPolicy = '';

    /**
     * Active sort ordering selector value.
     *
     * @var string
     *
     * @since  __DEPLOY_VERSION__
     */
    public string $listFullordering = 'domain ASC';

    /**
     * Search tools filter form.
     *
     * @var Form
     *
     * @since  __DEPLOY_VERSION__
     */
    public Form $filterForm;

    /**
     * Active search tool filters.
     *
     * @var array<string, mixed>
     *
     * @since  __DEPLOY_VERSION__
     */
    public array $activeFilters = [];

    /**
     * Total amount of list rows.
     *
     * @var int
     *
     * @since  __DEPLOY_VERSION__
     */
    public int $total = 0;

    /**
     * Display the policies view.
     *
     * @params string $tpl Template name.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $license              = Factory::getContainer()->get(LicenseService::class);
        $this->isPro          = $license->isPro() && !$license->isExpired();
        $this->licenseExpired = $license->isExpired();

        /** @var \NX\Component\Fediverse\Administrator\Model\PoliciesModel $model */
        $model = $this->getModel();

        $context = 'com_fediverse.policies';

        if ($app->input->exists('filter')) {
            $filter = (array) $app->input->get('filter', [], 'array');

            $search = trim((string) ($filter['search'] ?? ''));
            $policy = (string) ($filter['policy'] ?? '');
            if (!in_array($policy, ['allow', 'block'], true)) {
                $policy = '';
            }

            $app->setUserState($context . '.filter.search', $search);
            $app->setUserState($context . '.filter.policy', $policy);
        }

        if ($app->input->exists('list')) {
            $list = (array) $app->input->get('list', [], 'array');

            if (array_key_exists('fullordering', $list)) {
                $app->setUserState(
                    $context . '.list.fullordering',
                    $this->normaliseOrdering((string) $list['fullordering'])
                );
            }

            if (array_key_exists('limit', $list)) {
                $app->setUserState($context . '.list.limit', max(0, (int) $list['limit']));
            }
        }

        $this->filterSearch = trim((string) $app->getUserState($context . '.filter.search', ''));
        $this->filterPolicy = (string) $app->getUserState($context . '.filter.policy', '');
        if (!in_array($this->filterPolicy, ['allow', 'block'], true)) {
            $this->filterPolicy = '';
        }

        $this->listFullordering = $this->normaliseOrdering(
            (string) $app->getUserState($context . '.list.fullordering', 'domain ASC')
        );
        $limit = max(0, (int) $app->getUserState($context . '.list.limit', (int) $app->get('list_limit', 20)));
        $limitstart = (int) $app->getUserStateFromRequest($context . '.limitstart', 'limitstart', 0, 'uint');

        $total = $model->countList($this->filterSearch, $this->filterPolicy);

        if ($limit > 0 && $limitstart >= $total) {
            $pages = (int) max(0, ceil($total / $limit) - 1);
            $limitstart = $pages * $limit;
        }

        $app->setUserState($context . '.limitstart', $limitstart);

        $this->items = $model->getList(
            $limit,
            $limitstart,
            $this->filterSearch,
            $this->filterPolicy,
            $this->listFullordering
        );
        $this->pagination = new Pagination($total, $limitstart, $limit);
        $this->total = $total;
        $this->filterForm = Form::getInstance(
            'com_fediverse.policies.filter',
            JPATH_COMPONENT_ADMINISTRATOR . '/forms/filter_policies.xml',
            ['control' => '']
        );
        $this->filterForm->bind(
            [
                'filter' => [
                    'search' => $this->filterSearch,
                    'policy' => $this->filterPolicy,
                ],
                'list' => [
                    'fullordering' => $this->listFullordering,
                    'limit'        => $limit,
                ],
            ]
        );
        $this->activeFilters = array_filter(
            [
                'policy' => $this->filterPolicy,
            ],
            static fn(mixed $value): bool => $value !== null && $value !== ''
        );

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Normalise user-selected list ordering to valid toolbar options.
     *
     * @param   string  $fullOrdering  Raw full ordering value.
     *
     * @return  string  Safe full ordering value.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function normaliseOrdering(string $fullOrdering): string
    {
        $allowed = [
            'domain ASC',
            'domain DESC',
            'policy ASC',
            'policy DESC',
        ];

        return in_array($fullOrdering, $allowed, true) ? $fullOrdering : 'domain ASC';
    }

    /**
     * Add the policies toolbar.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_FEDIVERSE_POLICIES_TITLE'), 'shield');
        if ($this->isPro) {
            ToolbarHelper::addNew('policies.add');
            ToolbarHelper::editList('policies.edit');
            ToolbarHelper::deleteList('', 'policies.delete');
        }
    }
}
