<?php
// src/Controller/Auth/TelegramAuthController.php

namespace App\Controller\Auth;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/auth')]
class TelegramAuthController extends AbstractController
{
    private EntityManagerInterface $em;
//    private UserPasswordHasherInterface $passwordHasher;
    private string $botToken;

    public function __construct(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->em = $em;
//        $this->passwordHasher = $passwordHasher;
        $this->botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
    }

    #[Route('/telegram/callback', name: 'api_telegram_callback', methods: ['POST'])]
    public function telegramCallback(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse([
                'success' => false,
                'error' => 'No data provided'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Проверяем подпись Telegram
        if (!$this->validateTelegramData($data)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid Telegram signature'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $telegramId = (int) $data['id'];
        $telegramUsername = $data['username'] ?? null;
        $firstName = $data['first_name'] ?? '';

        // Ищем пользователя по telegramId
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['telegramId' => $telegramId]);

        if (!$user) {
            // Создаем нового пользователя
            $user = $this->createUser($telegramId, $telegramUsername, $firstName);
        } else {
            // Обновляем существующего пользователя
            $user->setUsername($telegramUsername ?? $user->getUsername());
        }

        $this->em->flush();

        // Генерируем JWT токен или другой токен
        $token = $this->generateToken($user);

        return new JsonResponse([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'telegramId' => $user->getTelegramId(),
                'roles' => $user->getRoles()
            ]
        ]);
    }

    #[Route('/telegram/validate', name: 'api_telegram_validate', methods: ['POST'])]
    public function validateTelegram(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['hash'])) {
            return new JsonResponse([
                'valid' => false,
                'error' => 'Invalid data'
            ]);
        }

        $isValid = $this->validateTelegramData($data);

        return new JsonResponse([
            'valid' => $isValid,
            'telegramId' => $isValid ? ($data['id'] ?? null) : null
        ]);
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

    private function createUser(int $telegramId, ?string $telegramUsername, string $firstName): User
    {
        $user = new User();
        $user->setTelegramId($telegramId);

        // Генерируем username
        $username = $telegramUsername ?: 'user_' . $telegramId;
        $user->setUsername($username);

        // Устанавливаем роль
        $user->setRoles(['ROLE_USER']);

        // Генерируем случайный пароль (не используется при Telegram auth)
//        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(16))));

        $this->em->persist($user);

        return $user;
    }

    private function generateToken(User $user): string
    {
        // Генерируем JWT токен
        // Или используйте lexik/jwt-authentication-bundle

        // Временное решение - возвращаем base64 encoded user info
        $tokenData = [
            'id' => $user->getId(),
            'telegramId' => $user->getTelegramId(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
            'exp' => time() + (24 * 60 * 60) // 24 часа
        ];

        return base64_encode(json_encode($tokenData));
    }
}
