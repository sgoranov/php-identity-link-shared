<?php
declare(strict_types=1);

namespace sgoranov\IdentityLinkShared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

class OpenApiController extends AbstractController
{
    public function openapi(): Response
    {
        if ($this->getParameter('kernel.environment') !== 'dev') {
            throw $this->createNotFoundException();
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/docs/openapi.yaml';

        if (!file_exists($filePath)) {
            throw new FileNotFoundException('OpenAPI spec not found.');
        }

        $response = new Response(file_get_contents($filePath));
        $response->headers->set('Content-Type', 'application/yaml');

        return $response;
    }
}