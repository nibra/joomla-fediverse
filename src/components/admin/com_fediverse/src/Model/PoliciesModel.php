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
use NX\Component\Fediverse\Administrator\Domain\Policy\DomainPolicy;

/**
 * PoliciesModel Class
 *
 * Provide database access for domain federation policies.
 *
 * @since  __DEPLOY_VERSION__
 */
final class PoliciesModel extends BaseModel
{
    /**
     * Initialize the policies model.
     *
     * @params DatabaseInterface $db     Database connection.
     * @params array             $config Model configuration.
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
     * Find the policy for a given domain.
     *
     * @params string $domain Hostname to look up.
     *
     * @return  ?DomainPolicy  Policy or null when no explicit rule exists.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function findByDomain(string $domain): ?DomainPolicy
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return null;
        }

        $query = $this->db->createQuery()
            ->select(['*'])
            ->from($this->db->quoteName('#__fediverse_domain_policies'))
            ->where($this->db->quoteName('domain') . ' = :domain')
            ->bind(':domain', $domain, ParameterType::STRING)
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadAssoc();

        return $row !== null ? DomainPolicy::fromRow($row) : null;
    }

    /**
     * Check whether a domain is explicitly blocked.
     *
     * @params string $domain Hostname to check.
     *
     * @return  bool  True when the domain has a block policy.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function isBlocked(string $domain): bool
    {
        $policy = $this->findByDomain($domain);

        return $policy !== null && $policy->isBlock();
    }

    /**
     * Fetch all domain policies ordered by domain.
     *
     * @params int    $limit   Maximum number of rows (0 = no limit).
     * @params int    $offset  Row offset.
     * @params string $search  Optional search term.
     * @params string $policy  Optional policy filter ('allow' or 'block').
     *
     * @return  array<int, array<string, mixed>>  List of policy rows.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getList(
        int $limit = 0,
        int $offset = 0,
        string $search = '',
        string $policy = '',
        string $fullOrdering = 'domain ASC'
    ): array
    {
        [$orderColumn, $orderDirection] = $this->normaliseOrdering(
            $fullOrdering,
            'domain',
            'ASC',
            ['domain', 'policy']
        );

        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__fediverse_domain_policies'))
            ->order($this->db->quoteName($orderColumn) . ' ' . $orderDirection);

        $search = trim($search);
        if ($search !== '') {
            $searchLike = '%' . str_replace(' ', '%', $search) . '%';
            $query->where(
                '('
                . $this->db->quoteName('domain') . ' LIKE :search1'
                . ' OR ' . $this->db->quoteName('reason') . ' LIKE :search2'
                . ')'
            )
                ->bind(':search1', $searchLike, ParameterType::STRING)
                ->bind(':search2', $searchLike, ParameterType::STRING);
        }

        if ($policy === DomainPolicy::POLICY_ALLOW || $policy === DomainPolicy::POLICY_BLOCK) {
            $query->where($this->db->quoteName('policy') . ' = :policy')
                ->bind(':policy', $policy, ParameterType::STRING);
        }

        if ($limit > 0) {
            $query->setLimit($limit, $offset);
        }

        $this->db->setQuery($query);

        return $this->db->loadAssocList() ?: [];
    }

    /**
     * Count all domain policy rows for optional filters.
     *
     * @params string $search Optional search term.
     * @params string $policy Optional policy filter ('allow' or 'block').
     *
     * @return  int  Total policy rows.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function countList(string $search = '', string $policy = ''): int
    {
        $query = $this->db->createQuery()
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__fediverse_domain_policies'));

        $search = trim($search);
        if ($search !== '') {
            $searchLike = '%' . str_replace(' ', '%', $search) . '%';
            $query->where(
                '('
                . $this->db->quoteName('domain') . ' LIKE :search1'
                . ' OR ' . $this->db->quoteName('reason') . ' LIKE :search2'
                . ')'
            )
                ->bind(':search1', $searchLike, ParameterType::STRING)
                ->bind(':search2', $searchLike, ParameterType::STRING);
        }

        if ($policy === DomainPolicy::POLICY_ALLOW || $policy === DomainPolicy::POLICY_BLOCK) {
            $query->where($this->db->quoteName('policy') . ' = :policy')
                ->bind(':policy', $policy, ParameterType::STRING);
        }

        $this->db->setQuery($query);
        $total = $this->db->loadResult();

        return $total !== null ? (int) $total : 0;
    }

    /**
     * Save or update a domain policy.
     *
     * @params string $domain Domain hostname.
     * @params string $policy Policy type ('allow' or 'block').
     * @params string $reason Optional reason.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function save(string $domain, string $policy, string $reason = ''): void
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return;
        }

        $policy = in_array($policy, [DomainPolicy::POLICY_ALLOW, DomainPolicy::POLICY_BLOCK], true)
            ? $policy
            : DomainPolicy::POLICY_BLOCK;

        $idQuery = $this->db->createQuery()
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__fediverse_domain_policies'))
            ->where($this->db->quoteName('domain') . ' = :domain')
            ->bind(':domain', $domain, ParameterType::STRING)
            ->setLimit(1);

        $this->db->setQuery($idQuery);
        $existingId = $this->db->loadResult();

        if ($existingId !== null) {
            $query = $this->db->createQuery()
                ->update($this->db->quoteName('#__fediverse_domain_policies'))
                ->set($this->db->quoteName('policy') . ' = :policy')
                ->set($this->db->quoteName('reason') . ' = :reason')
                ->where($this->db->quoteName('domain') . ' = :domain')
                ->bind(':policy', $policy, ParameterType::STRING)
                ->bind(':reason', $reason, ParameterType::STRING)
                ->bind(':domain', $domain, ParameterType::STRING);
        } else {
            $query = $this->db->createQuery()
                ->insert($this->db->quoteName('#__fediverse_domain_policies'))
                ->columns([
                    $this->db->quoteName('domain'),
                    $this->db->quoteName('policy'),
                    $this->db->quoteName('reason'),
                ])
                ->values(':domain, :policy, :reason')
                ->bind(':domain', $domain, ParameterType::STRING)
                ->bind(':policy', $policy, ParameterType::STRING)
                ->bind(':reason', $reason, ParameterType::STRING);
        }

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Delete the policy for a domain.
     *
     * @params string $domain Domain hostname.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function delete(string $domain): void
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return;
        }

        $query = $this->db->createQuery()
            ->delete($this->db->quoteName('#__fediverse_domain_policies'))
            ->where($this->db->quoteName('domain') . ' = :domain')
            ->bind(':domain', $domain, ParameterType::STRING);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Normalise full ordering input to a whitelisted column and direction.
     *
     * @param   string    $fullOrdering      User supplied ordering value.
     * @param   string    $defaultColumn     Fallback ordering column.
     * @param   string    $defaultDirection  Fallback ordering direction.
     * @param   string[]  $allowedColumns    Allowed ordering columns.
     *
     * @return  array{0:string,1:string}  Safe ordering tuple.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function normaliseOrdering(
        string $fullOrdering,
        string $defaultColumn,
        string $defaultDirection,
        array $allowedColumns
    ): array {
        $parts     = preg_split('/\s+/', trim($fullOrdering)) ?: [];
        $column    = (string) ($parts[0] ?? '');
        $direction = strtoupper((string) ($parts[1] ?? ''));

        if (!in_array($column, $allowedColumns, true)) {
            $column = $defaultColumn;
        }

        if ($direction !== 'ASC' && $direction !== 'DESC') {
            $direction = strtoupper($defaultDirection);
        }

        return [$column, $direction];
    }
}
