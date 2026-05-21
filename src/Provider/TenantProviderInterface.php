<?php

namespace sgoranov\IdentityLinkShared\Provider;

interface TenantProviderInterface
{
    public function getDbName(string $identifier): string;
}