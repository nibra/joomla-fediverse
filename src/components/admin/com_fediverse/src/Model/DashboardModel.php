<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Model;

use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * DashboardModel Class
 *
 * Provide summary data for the Fediverse dashboard.
 *
 * @since  __DEPLOY_VERSION__
 */
final class DashboardModel extends BaseModel
{
    /**
     * Initialize the dashboard model.
     *
     * Store the database dependency for summary queries.
     *
     * @params DatabaseInterface $db Database connection.
     * @params array $config Model configuration.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private DatabaseInterface $db, array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * Fetch dashboard summary data.
     *
     * Aggregate actor and delivery queue counts for display.
     *
     * @return  array<string,array<string,int>>  Summary data.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getSummary(): array
    {
        return [
            'actors'     => $this->getActorCounts(),
            'deliveries' => $this->getDeliveryCounts(),
        ];
    }

    /**
     * Fetch actor counts by type.
     *
     * Return counts for local and remote actors.
     *
     * @return  array<string,int>  Actor counts.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getActorCounts(): array
    {
        $counts = [
            'local'  => 0,
            'remote' => 0,
        ];

        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('type'),
                'COUNT(*) AS total',
            ])
            ->from($this->db->quoteName('#__fediverse_actors'))
            ->group($this->db->quoteName('type'));

        $this->db->setQuery($query);
        $rows = $this->db->loadAssocList() ?: [];

        foreach ($rows as $row) {
            $type = isset($row['type']) ? (string) $row['type'] : '';
            $total = isset($row['total']) ? (int) $row['total'] : 0;

            if (isset($counts[$type])) {
                $counts[$type] = $total;
            }
        }

        return $counts;
    }

    /**
     * Fetch delivery queue counts by state.
     *
     * Return counts for queued, delivering, delivered, and failed rows.
     *
     * @return  array<string,int>  Delivery counts.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getDeliveryCounts(): array
    {
        $counts = [
            'queued'     => 0,
            'delivering' => 0,
            'delivered'  => 0,
            'failed'     => 0,
        ];

        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('state'),
                'COUNT(*) AS total',
            ])
            ->from($this->db->quoteName('#__fediverse_delivery_queue'))
            ->group($this->db->quoteName('state'));

        $this->db->setQuery($query);
        $rows = $this->db->loadAssocList() ?: [];

        foreach ($rows as $row) {
            $state = isset($row['state']) ? (string) $row['state'] : '';
            $total = isset($row['total']) ? (int) $row['total'] : 0;

            if ($state === 'inflight') {
                $state = 'delivering';
            }

            if (isset($counts[$state])) {
                $counts[$state] += $total;
            }
        }

        return $counts;
    }
}
