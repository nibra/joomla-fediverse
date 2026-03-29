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
    case Pro      = 'pro';

    /**
     * Check whether this tier is a paid plan.
     *
     * @return  bool  True for Personal and Pro.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function isPaid(): bool
    {
        return $this !== self::Free;
    }

    /**
     * Check whether this tier is the Pro plan.
     *
     * @return  bool  True only for the Pro plan.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function isPro(): bool
    {
        return $this === self::Pro;
    }

    /**
     * Check whether this tier satisfies a minimum required tier.
     *
     * @param   self  $required  Minimum required tier.
     *
     * @return  bool  True when this tier is greater than or equal to required.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function isAtLeast(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }

    /**
     * Return sort rank for tier comparisons.
     *
     * @return  int  Rank value.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function rank(): int
    {
        return match ($this) {
            self::Free     => 0,
            self::Personal => 1,
            self::Pro      => 2,
        };
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
            self::Free     => 2,
            self::Personal => 6,
            self::Pro      => PHP_INT_MAX,
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
            self::Free     => 'Free',
            self::Personal => 'Personal',
            self::Pro      => 'Pro',
        };
    }
}
