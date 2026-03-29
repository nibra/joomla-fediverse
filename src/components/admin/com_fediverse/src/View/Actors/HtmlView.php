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
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
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
     * Whether the installation has an active paid plan.
     *
     * @var bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public bool $isPaid = false;

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
     * Active enabled filter.
     *
     * @var string
     *
     * @since  __DEPLOY_VERSION__
     */
    public string $filterEnabled = '';

    /**
     * Active actor record type filter.
     *
     * @var string
     *
     * @since  __DEPLOY_VERSION__
     */
    public string $filterType = '';

    /**
     * Active sort ordering selector value.
     *
     * @var string
     *
     * @since  __DEPLOY_VERSION__
     */
    public string $listFullordering = 'preferred_username ASC';

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
     * Total number of local actor records.
     *
     * @var int
     *
     * @since  __DEPLOY_VERSION__
     */
    public int $localActorCount = 0;

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
        $app = Factory::getApplication();
        $license         = Factory::getContainer()->get(LicenseService::class);
        $this->isPaid    = $license->isPaid() && !$license->isExpired();
        $this->actorLimit = $license->getTier()->actorLimit();

        /** @var \NX\Component\Fediverse\Administrator\Model\ActorsModel $model */
        $model = $this->getModel();

        $context = 'com_fediverse.actors';
        $keepTypeFilterPanelOpen = $app->input->getInt('actors_scope_changed', 0) === 1;

        if ($app->input->exists('filter')) {
            $filter = (array) $app->input->get('filter', [], 'array');

            $search  = trim((string) ($filter['search'] ?? ''));
            $enabled = (string) ($filter['enabled'] ?? '');
            $type    = (string) ($filter['type'] ?? '');
            if ($enabled !== '1' && $enabled !== '0') {
                $enabled = '';
            }
            if ($type !== 'local' && $type !== 'remote') {
                $type = '';
            }
            $app->setUserState($context . '.filter.search', $search);
            $app->setUserState($context . '.filter.enabled', $enabled);
            $app->setUserState($context . '.filter.type', $type);
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
        $this->filterEnabled = (string) $app->getUserState($context . '.filter.enabled', '');
        if ($this->filterEnabled !== '1' && $this->filterEnabled !== '0') {
            $this->filterEnabled = '';
        }
        $this->filterType = (string) $app->getUserState($context . '.filter.type', '');
        if ($this->filterType !== 'local' && $this->filterType !== 'remote') {
            $this->filterType = '';
        }

        $this->listFullordering = $this->normaliseOrdering(
            (string) $app->getUserState($context . '.list.fullordering', 'preferred_username ASC')
        );
        $limit = max(0, (int) $app->getUserState($context . '.list.limit', (int) $app->get('list_limit', 20)));
        $limitstart = (int) $app->getUserStateFromRequest($context . '.limitstart', 'limitstart', 0, 'uint');

        $total = $model->countList($this->filterType, $this->filterSearch, $this->filterEnabled);

        if ($limit > 0 && $limitstart >= $total) {
            $pages = (int) max(0, ceil($total / $limit) - 1);
            $limitstart = $pages * $limit;
        }

        $app->setUserState($context . '.limitstart', $limitstart);

        $this->items = $model->getList(
            $this->filterType,
            $limit,
            $limitstart,
            $this->filterSearch,
            $this->filterEnabled,
            $this->listFullordering
        );
        $this->pagination = new Pagination($total, $limitstart, $limit);
        $this->total = $total;
        $this->localActorCount = $model->countList('local');
        $this->filterForm = Form::getInstance(
            'com_fediverse.actors.filter',
            JPATH_COMPONENT_ADMINISTRATOR . '/forms/filter_actors.xml',
            ['control' => '']
        );
        $this->filterForm->bind(
            [
                'filter' => [
                    'search'  => $this->filterSearch,
                    'enabled' => $this->filterEnabled,
                    'type'    => $this->filterType,
                ],
                'list' => [
                    'fullordering' => $this->listFullordering,
                    'limit'        => $limit,
                ],
            ]
        );
        $this->activeFilters = array_filter(
            [
                'type'    => $this->filterType,
                'enabled' => $this->filterEnabled,
            ],
            static fn(mixed $value): bool => $value !== null && $value !== ''
        );
        if ($keepTypeFilterPanelOpen) {
            $this->activeFilters['_scope_keep_open'] = true;
        }

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
            'preferred_username ASC',
            'preferred_username DESC',
            'type ASC',
            'type DESC',
            'user_id ASC',
            'user_id DESC',
            'actor_type ASC',
            'actor_type DESC',
            'object_type ASC',
            'object_type DESC',
            'is_enabled ASC',
            'is_enabled DESC',
            'id ASC',
            'id DESC',
        ];

        return in_array($fullOrdering, $allowed, true) ? $fullOrdering : 'preferred_username ASC';
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

        ToolbarHelper::title(Text::_('COM_FEDIVERSE_ACTORS_TITLE'), 'users');
        ToolbarHelper::editList('actors.edit');
        ToolbarHelper::custom('actors.enable', 'publish', '', Text::_('COM_FEDIVERSE_ACTORS_ENABLE'), true);
        ToolbarHelper::custom('actors.disable', 'unpublish', '', Text::_('COM_FEDIVERSE_ACTORS_DISABLE'), true);

        if (
            $user->authorise('core.admin', 'com_fediverse')
            || $user->authorise('core.options', 'com_fediverse')
        ) {
            ToolbarHelper::link(
                'index.php?option=com_config&view=component&component=com_fediverse',
                Text::_('JTOOLBAR_OPTIONS'),
                'options'
            );
        }
    }
}
