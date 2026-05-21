<?php
declare(strict_types=1);

namespace sgoranov\IdentityLinkShared\EventListener;

use sgoranov\IdentityLinkShared\Provider\TenantProviderInterface;
use sgoranov\IdentityLinkShared\Service\TenantContext;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Kernel Request Listener responsible for intercepting HTTP requests and resolving the Tenant context.
 *
 * This listener hooks into the `kernel.request` event at an early stage. It acts as the gateway
 * for multi-tenant isolation, ensuring that any downstream logic (controllers, repositories, etc.)
 * executes safely within the resolved tenant's boundaries.
 *
 * Resolution Workflow:
 *  - Bypasses sub-requests (e.g., fragments, forwards) and checks if multi-tenancy is globally active.
 *  - Extracts the unique tenant identifier directly from the custom HTTP header (`X-Tenant-ID`).
 *  - Validates the tenant's existence and active status via the `TenantProviderInterface`.
 *  - Triggers the hot-swap initialization inside `TenantContext` to connect to the dedicated database.
 */
class TenantListener
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantProviderInterface $tenantProvider,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->tenantContext->isMultiTenancyEnabled()) {
            return;
        }

        $request = $event->getRequest();

        $identifier = $request->headers->get('X-Tenant-ID');
        if (!$identifier) {
            throw new BadRequestHttpException("Missing HTTP header 'X-Tenant-ID'.");
        }

        $dbName = $this->tenantProvider->getDbName($identifier);
        if (!$dbName) {
            throw new BadRequestHttpException(sprintf("Tenant '%s' not found or is inactive.", $identifier));
        }

        $this->tenantContext->initialize($identifier, $dbName);
    }
}