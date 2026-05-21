<?php
declare(strict_types=1);

namespace sgoranov\IdentityLinkShared\Provider;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TenantProvider implements TenantProviderInterface
{
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly Connection $masterConnection,
        private readonly CacheInterface $cache,
    ) {
    }

    public function getDbName(string $identifier): string
    {
        $normalizedIdentifier = strtolower($identifier);
        $cacheKey = sprintf('tenant_db_mapping:%s', hash('sha256', $normalizedIdentifier));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($normalizedIdentifier, $identifier) {
            $item->expiresAfter(self::CACHE_TTL);
            $dbName = $this->fetchDbNameFromMaster($normalizedIdentifier);

            if (empty($dbName)) {
                throw new \RuntimeException(
                    sprintf("Tenant database lookup failed or tenant is inactive for '%s'.", $identifier));
            }

            return $dbName;
        });
    }

    private function fetchDbNameFromMaster(string $identifier): ?string
    {
        $sql = 'SELECT db_name FROM tenants WHERE identifier = :identifier AND is_active = true LIMIT 1';

        try {
            $result = $this->masterConnection->fetchOne($sql, [
                'identifier' => $identifier
            ]);

            return $result ? (string) $result : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}