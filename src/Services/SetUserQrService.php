<?php

namespace App\Services;

use App\Repository\UserRepository;
use Symfony\Component\Security\Core\User\UserInterface;

readonly class SetUserQrService
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function setQr($link, $userId): bool
    {
        $user = $this->userRepository->find($userId);
        $user->setPaymentLink($link);
        return $this->userRepository->save($user);
    }

}
