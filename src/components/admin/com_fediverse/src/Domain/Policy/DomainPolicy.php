<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Domain\Policy;

/**
 * DomainPolicy Class
 *
 * Represent a per-domain federation policy (allow or block).
 *
 * @since  __DEPLOY_VERSION__
 */
final class DomainPolicy
{
    public const POLICY_ALLOW = 'allow';
    public const POLICY_BLOCK = 'block';

    /**
     * Initialize the domain policy.
     *
     * @params int $id Database identifier.
     * @params string $domain Hostname (e.g. mastodon.social).
     * @params string $policy Policy type ('allow' or 'block').
     * @params string $reason Optional reason.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        public readonly int $id,
        public readonly string $domain,
        public readonly string $policy,
        public readonly string $reason = '',
    ) {
    }

    /**
     * Check whether this policy blocks the domain.
     *
     * @return  bool  True when domain is blocked.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function isBlock(): bool
    {
        return $this->policy === self::POLICY_BLOCK;
    }

    /**
     * Create from a database row.
     *
     * @params array<string,mixed> $row Database row.
     *
     * @return  self  Domain policy instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id:     (int) ($row['id'] ?? 0),
            domain: (string) ($row['domain'] ?? ''),
            policy: (string) ($row['policy'] ?? self::POLICY_BLOCK),
            reason: (string) ($row['reason'] ?? ''),
        );
    }
}
