<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\View\Deliveries;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * HtmlView Class
 *
 * Render the Fediverse delivery queue list view.
 *
 * @since  __DEPLOY_VERSION__
 */
final class HtmlView extends BaseHtmlView
{
    /**
     * List of delivery queue rows.
     *
     * @var array<int, array<string, mixed>>
     *
     * @since  __DEPLOY_VERSION__
     */
    public array $items = [];

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
     * Active state filter.
     *
     * @var string
     *
     * @since  __DEPLOY_VERSION__
     */
    public string $filterState = '';

    /**
     * Active sort ordering selector value.
     *
     * @var string
     *
     * @since  __DEPLOY_VERSION__
     */
    public string $listFullordering = 'created_at DESC';

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
     * Display the deliveries list view.
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

        /** @var \NX\Component\Fediverse\Administrator\Model\DeliveryQueueModel $model */
        $model = $this->getModel();

        $context = 'com_fediverse.deliveries';

        if ($app->input->exists('filter')) {
            $filter = (array) $app->input->get('filter', [], 'array');

            $search = trim((string) ($filter['search'] ?? ''));
            $state  = (string) ($filter['state'] ?? '');
            if (!in_array($state, ['queued', 'inflight', 'delivered', 'failed', 'dead'], true)) {
                $state = '';
            }

            $app->setUserState($context . '.filter.search', $search);
            $app->setUserState($context . '.filter.state', $state);
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
        $this->filterState = (string) $app->getUserState($context . '.filter.state', '');
        if (!in_array($this->filterState, ['queued', 'inflight', 'delivered', 'failed', 'dead'], true)) {
            $this->filterState = '';
        }

        $this->listFullordering = $this->normaliseOrdering(
            (string) $app->getUserState($context . '.list.fullordering', 'created_at DESC')
        );
        $limit = max(0, (int) $app->getUserState($context . '.list.limit', (int) $app->get('list_limit', 20)));
        $limitstart = (int) $app->getUserStateFromRequest($context . '.limitstart', 'limitstart', 0, 'uint');

        $total = $model->countList($this->filterState, $this->filterSearch);

        if ($limit > 0 && $limitstart >= $total) {
            $pages = (int) max(0, ceil($total / $limit) - 1);
            $limitstart = $pages * $limit;
        }

        $app->setUserState($context . '.limitstart', $limitstart);

        $this->items = $model->getList(
            $this->filterState,
            $limit,
            $limitstart,
            $this->filterSearch,
            $this->listFullordering
        );
        $this->pagination = new Pagination($total, $limitstart, $limit);
        $this->total = $total;
        $this->filterForm = Form::getInstance(
            'com_fediverse.deliveries.filter',
            JPATH_COMPONENT_ADMINISTRATOR . '/forms/filter_deliveries.xml',
            ['control' => '']
        );
        $this->filterForm->bind(
            [
                'filter' => [
                    'search' => $this->filterSearch,
                    'state'  => $this->filterState,
                ],
                'list' => [
                    'fullordering' => $this->listFullordering,
                    'limit'        => $limit,
                ],
            ]
        );
        $this->activeFilters = array_filter(
            [
                'state' => $this->filterState,
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
            'created_at DESC',
            'created_at ASC',
            'id DESC',
            'id ASC',
            'state DESC',
            'state ASC',
            'attempts DESC',
            'attempts ASC',
        ];

        return in_array($fullOrdering, $allowed, true) ? $fullOrdering : 'created_at DESC';
    }

    /**
     * Add the deliveries toolbar.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_FEDIVERSE_DELIVERIES_TITLE'), 'send');
        ToolbarHelper::deleteList('', 'deliveries.delete');
    }
}
