<?php
// src/Security/TelegramAuthenticator.php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class TelegramAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private EntityManagerInterface $entityManager;
    private UrlGeneratorInterface $urlGenerator;
    private string $botToken;

    public function __construct(
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator,
        string $botToken
    ) {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
        $this->botToken = $botToken;
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        // Редирект на страницу входа
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    public function supports(Request $request): ?bool
    {
        // Поддерживаем только GET запросы на callback URL
        return $request->isMethod('GET') &&
            $request->query->has('hash') &&
            $request->query->has('id');
    }

    public function authenticate(Request $request): Passport
    {
        $authData = $request->query->all();

        // Проверяем подпись Telegram
        if (!$this->validateTelegramData($authData)) {
            throw new CustomUserMessageAuthenticationException('Invalid Telegram signature');
        }

        $telegramId = (int) $authData['id'];

        return new SelfValidatingPassport(
            new UserBadge((string) $telegramId, function() use ($telegramId, $authData) {
                return $this->getOrCreateUser($telegramId, $authData);
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Редирект после успешной авторизации
        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add('error', $exception->getMessage());

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    private function validateTelegramData(array $data): bool
    {
        if (!isset($data['hash'])) {
            return false;
        }

        $checkHash = $data['hash'];
        unset($data['hash']);

        ksort($data);

        $dataCheckArr = [];
        foreach ($data as $key => $value) {
            $dataCheckArr[] = $key . '=' . $value;
        }

        $dataCheckString = implode("\n", $dataCheckArr);
        $secretKey = hash('sha256', $this->botToken, true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        return hash_equals($hash, $checkHash);
    }

    private function getOrCreateUser(int $telegramId, array $authData): User
    {
        $userRepository = $this->entityManager->getRepository(User::class);

        // Ищем существующего пользователя
        $user = $userRepository->findOneBy(['telegramId' => $telegramId]);

        if ($user) {
            // Обновляем данные пользователя
            $this->updateUserFromTelegram($user, $authData);
            return $user;
        }

        // Создаем нового пользователя
        return $this->createUserFromTelegram($telegramId, $authData);
    }

    private function createUserFromTelegram(int $telegramId, array $authData): User
    {
        $user = new User();
        $user->setTelegramId($telegramId);
        $user->setUsername($authData['username'] ?? 'tg_' . $telegramId);
//        $user->setFirstName($authData['first_name'] ?? '');
//        $user->setLastName($authData['last_name'] ?? '');
//
//        // Генерируем email
//        $email = $authData['username'] ?
//            $authData['username'] . '@telegram.com' :
//            'user_' . $telegramId . '@telegram.com';
//        $user->setEmail($email);
//
//        // Устанавливаем пароль (случайный, так как вход через Telegram)
//        $user->setPassword(password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT));

        // Устанавливаем роли
        $user->setRoles(['ROLE_USER']);

        // Сохраняем
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function updateUserFromTelegram(User $user, array $authData): void
    {
        $updated = false;

        if (isset($authData['username']) && $user->getUsername() !== $authData['username']) {
            $user->setUsername($authData['username']);
            $updated = true;
        }

//        if (isset($authData['first_name']) && $user->getFirstName() !== $authData['first_name']) {
//            $user->setFirstName($authData['first_name']);
//            $updated = true;
//        }
//
//        if (isset($authData['last_name']) && $user->getLastName() !== $authData['last_name']) {
//            $user->setLastName($authData['last_name']);
//            $updated = true;
//        }

        if ($updated) {
            $this->entityManager->flush();
        }
    }
}
