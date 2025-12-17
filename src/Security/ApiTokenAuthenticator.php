<?php

// src/Security/ApiTokenAuthenticator.php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiTokenAuthenticator extends AbstractAuthenticator
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('X-AUTH-TOKEN') ||
            $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            throw new CustomUserMessageAuthenticationException('No API token provided');
        }

        // Декодируем токен (в вашем случае это base64 JSON)
        $tokenData = json_decode(base64_decode($token), true);

        if (!$tokenData || !isset($tokenData['id'])) {
            throw new CustomUserMessageAuthenticationException('Invalid token');
        }

        // Проверяем срок действия
        if (isset($tokenData['exp']) && $tokenData['exp'] < time()) {
            throw new CustomUserMessageAuthenticationException('Token expired');
        }

        $userId = $tokenData['id'];

        return new SelfValidatingPassport(
            new UserBadge($userId, function() use ($userId) {
                $user = $this->em->getRepository(User::class)->find($userId);

                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('User not found');
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // Продолжаем выполнение запроса
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => 'Authentication failed',
            'message' => $exception->getMessage()
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function getTokenFromRequest(Request $request): ?string
    {
        // Проверяем заголовок Authorization
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader && preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Проверяем кастомный заголовок
        return $request->headers->get('X-AUTH-TOKEN');
    }
}
