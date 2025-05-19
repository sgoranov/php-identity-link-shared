<?php
declare(strict_types=1);

namespace sgoranov\IdentityLinkShared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ZxcvbnPhp\Zxcvbn;

class PasswordController extends AbstractController
{
    public function checkPasswordStrength(Request $request): JsonResponse
    {
        // Retrieve the password from the request
        $password = $request->request->get('password');
        if (!$password) {
            return new JsonResponse(['error' => 'Password is required.'], Response::HTTP_BAD_REQUEST);
        }

        // Evaluate the password strength
        $zxcvbn = new Zxcvbn();
        $strength = $zxcvbn->passwordStrength($password);

        return new JsonResponse($strength);
    }
}