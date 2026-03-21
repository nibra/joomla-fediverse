<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\License;

/**
 * LicenseTier Enum
 *
 * Represents the license tier of the installation.
 *
 * @since  __DEPLOY_VERSION__
 */
enum LicenseTier: string
{
    case Free     = 'free';
    case Personal = 'personal';
    case Developer = 'developer';
    case Agency   = 'agency';

    /**
     * Check whether this tier includes Pro features.
     *
     * @return  bool  True if this is a paid tier.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function isPro(): bool
    {
        return $this !== self::Free;
    }

    /**
     * Maximum number of actors allowed for this tier.
     *
     * @return  int  Actor limit (PHP_INT_MAX = unlimited).
     *
     * @since  __DEPLOY_VERSION__
     */
    public function actorLimit(): int
    {
        return match ($this) {
            self::Free      => 1,
            self::Personal  => 3,
            self::Developer => 10,
            self::Agency    => PHP_INT_MAX,
        };
    }

    /**
     * Human-readable label for this tier.
     *
     * @return  string  Tier label.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function label(): string
    {
        return match ($this) {
            self::Free      => 'Free',
            self::Personal  => 'Personal',
            self::Developer => 'Developer',
            self::Agency    => 'Agency',
        };
    }
}
