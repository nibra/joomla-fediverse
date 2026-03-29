<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Event;

/**
 * ConfigurationExportedEvent Class
 *
 * Represent exporting a portable configuration package.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ConfigurationExportedEvent extends AbstractFediverseDomainEvent
{
    public const NAME = 'onFediverseConfigurationExported';

    /**
     * Initialize the event.
     *
     * @params int $userId Acting Joomla user id.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(int $userId)
    {
        parent::__construct(
            $userId,
            [
                'package_type' => 'configuration',
                'user_id' => $userId,
            ]
        );
    }
}
