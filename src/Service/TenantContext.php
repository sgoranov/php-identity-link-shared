<?php
declare(strict_types=1);

namespace sgoranov\IdentityLinkShared\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use sgoranov\IdentityLinkShared\Entity\Configuration;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TenantContext
{
    private bool $multiTenancyEnabled = false;
    private ?string $tenantId = null;
    private array $settingsCache = [];
    private bool $isInitialized = false;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    public function isMultiTenancyEnabled(): bool
    {
        return $this->multiTenancyEnabled;
    }

    public function setMultiTenancyEnabled(bool $enabled): void
    {
        $this->multiTenancyEnabled = $enabled;
    }

    /**
     * Initializes the tenant context for the current lifecycle request.
     *
     * Sets the global tenant identifier and triggers the runtime database hot-swap.
     * If multi-tenancy is disabled globally via configuration, or if the context
     * has already been locked/initialized, the operation aborts silently.
     *
     */
    public function initialize(string $tenantId, string $dbName): void
    {
        if ($this->isInitialized || !$this->multiTenancyEnabled) {
            return;
        }

        $this->tenantId = $tenantId;
        $this->switchDoctrineConnection($dbName);

        $this->isInitialized = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->multiTenancyEnabled) {
            if (!$this->isInitialized) {
                throw new \LogicException("TenantContext must be initialized before accessing configurations.");
            }

            if (empty($this->settingsCache)) {
                $this->loadSettings();
            }

            return $this->settingsCache[$key] ?? $default;
        }

        if ($this->parameterBag->has($key)) {
            return $this->parameterBag->get($key);
        }

        return $default;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function isInitialized(): bool
    {
        return $this->isInitialized;
    }

    /**
     * Executes a cache operation on the provided pool with automatic tenant isolation.
     *
     * This method acts as a decorator for any Symfony Cache Pool. By passing the pool
     * as an argument (Method Injection), we decouple this context from specific cache
     * configurations, allowing it to isolate app, system, or custom pools interchangeably.
     */
    public function cache(CacheInterface $cachePool, string $key, callable $callback, int $ttl = 3600): mixed
    {
        if ($this->isInitialized && $this->tenantId !== null) {
            $scopedKey = sprintf('tenant_%s:%s', strtolower($this->tenantId), $key);
            $tag = 'tenant_' . strtolower($this->tenantId);
        } else {
            $scopedKey = sprintf('global:%s', $key);
            $tag = 'system_cache';
        }

        return $cachePool->get($scopedKey, function (ItemInterface $item) use ($callback, $ttl, $tag) {
            $item->expiresAfter($ttl);
            if (method_exists($item, 'tag')) {
                $item->tag($tag);
            }

            return $callback();
        });
    }

    /**
     * Creates a tenant-isolated rate limiter from the provided factory.
     *
     * Decouples rate limiting from specific security workflows. By injecting the factory
     * directly into the method, we can isolate login, registration, or API limiters
     * interchangeably. Automatically falls back to a global prefix if no tenant is active.
     */
    public function limit(RateLimiterFactory $limiterFactory, string $key): LimiterInterface
    {
        if ($this->isInitialized && $this->tenantId !== null) {
            $scopedKey = sprintf('%s_rate_limit_%s', strtolower($this->tenantId), $key);
        } else {
            $scopedKey = sprintf('global_rate_limit_%s', $key);
        }

        return $limiterFactory->create($scopedKey);
    }

    /**
     * Dynamically swaps the active Doctrine database connection parameters at runtime.
     *
     * Doctrine DBAL connection parameters are immutable by design once the connection
     * is established. To bypass this, we close any open connection and utilize PHP
     * Reflection to force-inject the new 'dbname' parameter directly into the private
     * 'params' property of the Doctrine Connection instance.
     */
    private function switchDoctrineConnection(string $dbName): void
    {
        /** @var Connection $connection */
        $connection = $this->entityManager->getConnection();

        if ($connection->isConnected()) {
            $connection->close();
        }

        $params = $connection->getParams();
        $params['dbname'] = $dbName;

        $reflectionProperty = new \ReflectionProperty(Connection::class, 'params');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($connection, $params);
    }

    private function loadSettings(): void
    {
        try {
            $repository = $this->entityManager->getRepository(Configuration::class);
            $configs = $repository->findAll();

            foreach ($configs as $config) {
                $this->settingsCache[$config->getConfigKey()] = $config->getConfigValue();
            }
        } catch (\Exception $e) {
            $this->settingsCache = [];
        }
    }
}