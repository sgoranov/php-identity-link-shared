<?php
declare(strict_types=1);

namespace sgoranov\IdentityLinkShared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class PingController extends AbstractController
{
    public function ping(): Response
    {
        return new Response('pong');
    }
}