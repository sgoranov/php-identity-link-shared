<?php
declare(strict_types=1);

namespace sgoranov\IdentityLinkShared\Session;

use sgoranov\IdentityLinkShared\Service\TenantContext;

/**
 * Custom Redis Session Handler with Multi-Tenant Isolation capabilities.
 *
 * In a shared infrastructure environment using a single Redis instance, session data
 * must be strictly sandboxed to prevent session hijacking and cross-tenant data leakage.
 * This class decorates standard session interactions by transparently altering the key namespace.
 *
 * Garbage collection (gc) natively returns true because Redis manages session expiration
 * automatically at the database level via Time-To-Live parameters (SETEX).
 */
class TenantAwareRedisSessionHandler implements \SessionHandlerInterface
{
    private string $basePrefix = 'sess:';
    private int $ttl = 3600;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly mixed $redisClient,
    ){
        if (!\extension_loaded('redis')) {
            throw new \RuntimeException(
                sprintf("The 'ext-redis' extension is required to use %s.", __CLASS__)
            );
        }

        if (!$redisClient instanceof \Redis) {
            throw new \InvalidArgumentException(
                sprintf("Expected an instance of \Redis, %s given.", get_debug_type($redisClient))
            );
        }
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        return $this->redisClient->get($this->getTenantPrefix() . $id) ?: '';
    }

    public function write(string $id, string $data): bool
    {
        return $this->redisClient->setex($this->getTenantPrefix() . $id, $this->ttl, $data);
    }

    public function destroy(string $id): bool
    {
        return (bool) $this->redisClient->del($this->getTenantPrefix() . $id);
    }

    public function gc(int $max_lifetime): int|false
    {
        return true;
    }

    public function setTtl(int $ttl): void
    {
        $this->ttl = $ttl;
    }

    public function setBasePrefix(string $basePrefix): void
    {
        $this->basePrefix = $basePrefix;
    }

    private function getTenantPrefix(): string
    {
        if ($this->tenantContext->isInitialized()) {
            return sprintf('tenant:%s:%s', strtolower($this->tenantContext->getTenantId()), $this->basePrefix);
        }

        return $this->basePrefix;
    }
}