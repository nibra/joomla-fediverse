<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Announce;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use NX\Component\Fediverse\Administrator\Service\Site\BaseUrlProviderInterface;

/**
 * NodeInfoService Class
 *
 * Provide Node Info services.
 *
 * @since  __DEPLOY_VERSION__
 */

final class NodeInfoService
{
    /**
     * Initialize the NodeInfo service.
     *
     * Store dependencies for base URL resolution and counts.
     *
     * @params BaseUrlProviderInterface $baseUrl Base URL provider.
     * @params DatabaseInterface $db Database connection.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private BaseUrlProviderInterface $baseUrl,
        private DatabaseInterface $db,
    ) {
    }

    /**
     * Build the NodeInfo well-known document.
     *
     * Return discovery links for the NodeInfo schema.
     *
     * @return  array<string,mixed>  NodeInfo discovery payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function buildWellKnown(): array
    {
        $base = rtrim($this->baseUrl->getBaseUrl(), '/');

        return [
            'links' => [
                [
                    'rel'  => 'http://nodeinfo.diaspora.software/ns/schema/2.0',
                    'href' => $base . '/ap/nodeinfo/2.0',
                ],
            ],
        ];
    }

    /**
     * Build the NodeInfo document.
     *
     * Return a NodeInfo 2.0 payload describing the local node.
     *
     * @return  array<string,mixed>  NodeInfo document payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function buildDocument(): array
    {
        return [
            'version' => '2.0',
            'software' => [
                'name'    => 'joomla',
                'version' => $this->getSoftwareVersion(),
            ],
            'protocols' => ['activitypub'],
            'services' => [
                'inbound'  => [],
                'outbound' => [],
            ],
            'openRegistrations' => $this->isRegistrationOpen(),
            'usage' => [
                'users' => [
                    'total'          => $this->countLocalActors(),
                    'activeHalfyear' => $this->countActiveActors(180),
                    'activeMonth'    => $this->countActiveActors(30),
                ],
                'localPosts'    => $this->countLocalPosts(),
                'localComments' => 0,
            ],
            'metadata' => (object) [],
        ];
    }

    /**
     * Determine whether user registration is open.
     *
     * Read the com_users configuration flag.
     *
     * @return  bool  True when registrations are open.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function isRegistrationOpen(): bool
    {
        try {
            $params = ComponentHelper::getParams('com_users');
        } catch (\Throwable) {
            return false;
        }

        return (int) $params->get('allowUserRegistration', 0) === 1;
    }

    /**
     * Count local, enabled actors.
     *
     * @return  int  Local actor count.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function countLocalActors(): int
    {
        try {
            $query = $this->db->createQuery()
                ->select('COUNT(*)')
                ->from($this->db->quoteName('#__fediverse_actors'))
                ->where($this->db->quoteName('type') . ' = :type')
                ->where($this->db->quoteName('is_enabled') . ' = :enabled')
                ->bind(':type', 'local', ParameterType::STRING)
                ->bind(':enabled', 1, ParameterType::INTEGER);

            $this->db->setQuery($query);
            $result = $this->db->loadResult();

            return is_numeric($result) ? (int) $result : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Count actors with outbox activity within a rolling window.
     *
     * Return the number of distinct local actors that posted within the last N days.
     *
     * @params int $days Rolling window in days.
     *
     * @return  int  Active actor count.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function countActiveActors(int $days): int
    {
        try {
            $cutoff = (new \DateTimeImmutable())->modify('-' . max(1, $days) . ' days')->format('Y-m-d H:i:s');

            $query = $this->db->createQuery()
                ->select('COUNT(DISTINCT ' . $this->db->quoteName('local_actor_id') . ')')
                ->from($this->db->quoteName('#__fediverse_outbox'))
                ->where($this->db->quoteName('created_at') . ' >= :cutoff')
                ->bind(':cutoff', $cutoff, ParameterType::STRING);

            $this->db->setQuery($query);
            $result = $this->db->loadResult();

            return is_numeric($result) ? (int) $result : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Count local posts.
     *
     * Count Create activities in the outbox.
     *
     * @return  int  Local post count.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function countLocalPosts(): int
    {
        try {
            $query = $this->db->createQuery()
                ->select('COUNT(*)')
                ->from($this->db->quoteName('#__fediverse_outbox'))
                ->where($this->db->quoteName('type') . ' = :type')
                ->bind(':type', 'Create', ParameterType::STRING);

            $this->db->setQuery($query);
            $result = $this->db->loadResult();

            return is_numeric($result) ? (int) $result : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Resolve the Joomla software version.
     *
     * @return  string  Joomla version string.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getSoftwareVersion(): string
    {
        $version = defined('JVERSION') ? (string) JVERSION : '';
        $version = trim($version);

        return $version !== '' ? $version : 'unknown';
    }
}
