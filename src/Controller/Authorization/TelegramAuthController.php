<?php
// src/Controller/Auth/TelegramAuthController.php

namespace App\Controller\Authorization;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use App\Security\TelegramAuthenticator;

class TelegramAuthController extends AbstractController
{
    #[Route('/telegram-auth', name: 'telegram_auth')]
    public function telegramAuth(Request $request): Response
    {
        return $this->render('auth/telegram_login.html.twig');
    }

    #[Route('/telegram-callback', name: 'telegram_callback')]
    public function telegramCallback(
        Request $request,
        EntityManagerInterface $em,
        UserAuthenticatorInterface $userAuthenticator,
        TelegramAuthenticator $telegramAuthenticator
    ): Response {
        // Проверяем подпись Telegram
        $authData = $request->query->all();

        // Валидация данных
        if (!isset($authData['id'], $authData['hash'])) {
            $this->addFlash('error', 'Invalid Telegram data');
            return $this->redirectToRoute('app_login');
        }

        $telegramId = (int) $authData['id'];
        $username = $authData['username'] ?? null;
        $firstName = $authData['first_name'] ?? null;
        $lastName = $authData['last_name'] ?? null;

        // Ищем пользователя по telegramId
        $user = $em->getRepository(User::class)->findOneBy(['telegramId' => $telegramId]);

        if (!$user) {
            // Проверяем, есть ли авторизованный пользователь (для привязки Telegram)
            $currentUser = $this->getUser();

            if ($currentUser) {
                // Привязываем Telegram к существующему пользователю
                $currentUser->setTelegramId($telegramId);
                $em->flush();

                $this->addFlash('success', 'Telegram успешно привязан!');
                return $this->redirectToRoute('app_profile');
            } else {
                // Создаем нового пользователя
                $user = $this->createUserFromTelegramData($telegramId, $username, $firstName, $lastName, $em);

                if (!$user) {
                    $this->addFlash('error', 'Не удалось создать пользователя');
                    return $this->redirectToRoute('app_register');
                }
            }
        }

        // Авторизуем пользователя
        return $userAuthenticator->authenticateUser(
            $user,
            $telegramAuthenticator,
            $request
        );
    }

    #[Route('/link-telegram', name: 'link_telegram')]
    public function linkTelegram(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return $this->render('auth/link_telegram.html.twig');
    }

    #[Route('/unlink-telegram', name: 'unlink_telegram')]
    public function unlinkTelegram(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        $user->setTelegramId(null);
        $em->flush();

        $this->addFlash('success', 'Telegram успешно отвязан');
        return $this->redirectToRoute('app_profile');
    }

    private function createUserFromTelegramData(
        int $telegramId,
        ?string $username,
        ?string $firstName,
        ?string $lastName,
        EntityManagerInterface $em
    ): ?User {
        // Генерируем уникальное имя пользователя
        $baseUsername = $username ?: 'tg_user_' . $telegramId;
        $finalUsername = $baseUsername;
        $counter = 1;

        // Проверяем уникальность имени пользователя
        while ($em->getRepository(User::class)->findOneBy(['username' => $finalUsername])) {
            $finalUsername = $baseUsername . '_' . $counter;
            $counter++;
        }

        $user = new User();
        $user->setTelegramId($telegramId);
        $user->setUsername($finalUsername);
//        $user->setFirstName($firstName);
//        $user->setLastName($lastName);

        // Устанавливаем email на основе Telegram (можно изменить)
//        $user->setEmail("telegram_{$telegramId}@example.com");

        // Устанавливаем случайный пароль (пользователь сможет изменить его позже)
//        $user->setPassword(password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT));

        // Активируем пользователя
//        $user->setIsActive(true);

        try {
            $em->persist($user);
            $em->flush();

            return $user;
        } catch (\Exception $e) {
            // Логируем ошибку
            error_log('Error creating user from Telegram: ' . $e->getMessage());
            return null;
        }
    }
}
