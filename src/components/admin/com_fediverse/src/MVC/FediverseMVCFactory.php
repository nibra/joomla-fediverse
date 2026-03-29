<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\MVC;

use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\MVC\Model\ModelInterface;
use Joomla\Database\DatabaseInterface;
use NX\Component\Fediverse\Administrator\Model\ActorsModel;
use NX\Component\Fediverse\Administrator\Model\DeliveryQueueModel;
use NX\Component\Fediverse\Administrator\Model\DashboardModel;
use NX\Component\Fediverse\Administrator\Model\DiagnosticsModel;
use NX\Component\Fediverse\Administrator\Model\FollowersModel;
use NX\Component\Fediverse\Administrator\Model\InboundActivitiesModel;
use NX\Component\Fediverse\Administrator\Model\InboxModel;
use NX\Component\Fediverse\Administrator\Model\KeysModel;
use NX\Component\Fediverse\Administrator\Model\OAuthModel;
use NX\Component\Fediverse\Administrator\Model\OutboxModel;
use NX\Component\Fediverse\Administrator\Model\PoliciesModel;
use NX\Component\Fediverse\Administrator\Model\UsersModel;
use NX\Component\Fediverse\Administrator\Model\WebhooksModel;
use Psr\Log\LoggerInterface;

/**
 * FediverseMVCFactory Class
 *
 * Provide Fediverse MVC MVC utilities.
 *
 * @since  __DEPLOY_VERSION__
 */
final class FediverseMVCFactory extends MVCFactory
{
    /**
     * Initialize the MVC factory.
     *
     * Store the database dependency for model creation.
     *
     * @params DatabaseInterface $db Database connection.
     * @params string $namespace Component namespace.
     * @params ?LoggerInterface $logger Optional logger.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private DatabaseInterface $db, ?LoggerInterface $logger = null)
    {
        parent::__construct('\\NX\\Component\\Fediverse', $logger);
    }

    /**
     * Create a model instance.
     *
     * Resolve a DB-only model for the provided name.
     *
     * @params mixed $name Model name.
     * @params string $prefix Model prefix.
     * @params array $config Model configuration.
     *
     * @return  ModelInterface  Model instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function createModel($name, $prefix = '', array $config = []): ModelInterface
    {
        $normalized = strtolower((string) $name);
        if (str_ends_with($normalized, 'model')) {
            $normalized = substr($normalized, 0, -5);
        }

        return match ($normalized) {
            'actor',
            'actors'        => new ActorsModel($this->db, $config),
            'keys'          => new KeysModel($this->db, $config),
            'followers'     => new FollowersModel($this->db, $config),
            'inboxitems',
            'inbox'         => new InboxModel($this->db, $config),
            'inboundactivities' => new InboundActivitiesModel($this->db, $config),
            'outbox'        => new OutboxModel($this->db, $config),
            'deliveries',
            'deliveryqueue' => new DeliveryQueueModel($this->db, $config),
            'dashboard'     => new DashboardModel($this->db, $config),
            'diagnostics'   => new DiagnosticsModel($this->db, $config),
            'policy',
            'policies'      => new PoliciesModel($this->db, $config),
            'oauth'         => new OAuthModel($this->db, $config),
            'users'         => new UsersModel($this->db, $config),
            'webhook',
            'webhooks'      => new WebhooksModel($this->db, $config),
            default         => parent::createModel($name, $prefix, $config),
        };
    }
}
