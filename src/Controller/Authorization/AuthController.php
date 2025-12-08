<?php

namespace App\Controller\Authorization;


use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    public function __construct(

        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly JWTTokenManagerInterface $jwtManager
    )
    {
    }

    #[Route('/api/telegram/webapp', name: 'telegram_webapp', methods: ['POST'])]
    public function webApp(Request $request): JsonResponse
    {
        $data = json_decode(base64_decode($request->request->get('tgAuthResult')), true);
        if (!$this->validateTelegramAuth($data)) {
            return new JsonResponse(['error' => 'invalid'], 403);
        }

        $user = $this->userRepository->findOneBy(['telegramId' => $data['id']]);
        if (!$user) {
            $user = new User();
            $user->setTelegramId((int)$data['id']);
            $user->setUsername($data['username'] ?? 'user_' . $data['id']);
            $this->em->persist($user);
            $this->em->flush();
        }

        $token = $this->jwtManager->create($user);

        return $this->json(['token'=> $token]);
    }

    private function validateTelegramAuth(array $data): bool
    {
        $hash = $data['hash'] ?? '';
        unset($data['hash']);

        $checkArr = [];
        foreach ($data as $k => $v) {
            $checkArr[] = $k . '=' . $v;
        }
        sort($checkArr);
        $secret = hash('sha256', $_ENV['TELEGRAM_BOT_TOKEN'], true);
        $calcHash = hash_hmac('sha256', implode("\n", $checkArr), $secret);

        return hash_equals($calcHash, $hash);
    }

}
