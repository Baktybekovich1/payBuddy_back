<?php

namespace App\Controller;

use App\Dto\LinkDto;
use App\Services\MbankQrService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class QrController extends AbstractController
{
    public function __construct(
        private readonly MbankQrService $qrService
    )
    {
    }

    #[Route('/qr', name: 'qr', methods: ['GET'])]
    public function qr(): JsonResponse
    {
        $newLink = $this->qrService->generateQrWithAmount('https://app.mbank.kg/qr/#00020101021132440012c2c.mbank.kg01020210129965037944001302125204999953034175907AKAI%20K.6304c3bc', 55.32);

        return $this->json(['qr_link' => $newLink]);
    }

//    #[Route('/qr/{amount}', name: 'qr_with_amount')]
//    public function qr(float $amount, MbankQrService $service): Response
//    {
//        $baseLink = $this->getUser()->getPaymentLink(); // ссылка без суммы
//        $newLink = $service->generateQrWithAmount($baseLink, $amount);
//
//        return $this->json(['qr_link' => $newLink]);
//    }

}
