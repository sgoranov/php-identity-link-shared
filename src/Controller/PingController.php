<?php
declare(strict_types=1);

namespace sgoranov\PHPIdentityLinkShared\Controller;

use sgoranov\PHPIdentityLinkShared\Api\DTO\AbstractQueryRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class PingController extends AbstractController
{
    public function ping(AbstractQueryRequest $request): Response
    {
        return new Response('pong');
    }
}