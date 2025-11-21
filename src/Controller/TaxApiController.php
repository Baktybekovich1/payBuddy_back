<?php

namespace App\Controller;

use App\Dto\LinkDto;
use App\Services\TaxApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TaxApiController extends AbstractController
{

    public function __construct(private readonly HttpClientInterface $httpClient, private readonly TaxApiService $taxApiService)
    {
    }

    #[Route('/api/v1/ticket', name: 'api_ticket', methods: ['POST'])]
    public function getTicket(#[MapRequestPayload] LinkDto $linkDto): JsonResponse
    {
        $link = $linkDto->link ?? null;

        if (!$link) {
            return $this->json([
                'success' => false,
                'error' => 'URL is required'
            ], 400);
        }
        try {
            // Делаем запрос к налоговому API по полученной ссылке
            $response = $this->httpClient->request('GET', $link);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent();
            $taxData = json_decode($content, true);

            // Возвращаем данные с налогового сервиса
            return $this->json(
                $this->taxApiService->TaxApi($taxData)
            );

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to fetch data from tax service: ' . $e->getMessage()
            ], 500);
        }
    }
//    #[Route('/api/v2/ticket', name: 'api_ticket2', methods: ['POST'])]
//    public function getTicke(#[MapRequestPayload] LinkDto $linkDto): JsonResponse
//    {
//        $link = $linkDto->link ?? null;
//
//        if (!$link) {
//            return $this->json([
//                'success' => false,
//                'error' => 'URL is required'
//            ], 400);
//        }
//        try {
//            // Делаем запрос к налоговому API по полученной ссылке
//            $response = $this->httpClient->request('GET', $link);
//
//            $statusCode = $response->getStatusCode();
//            $content = $response->getContent();
//            $taxData = json_decode($content, true);
//
//            return $this->json([
//                $this->taxApiService->TaxApi($taxData)
//            ]);
//            // Возвращаем данные с налогового сервиса
////            return $this->json([
////                'success' => true,
////                'data' => $taxData
////            ]);
//
//        } catch (\Exception $e) {
//            return $this->json([
//                'success' => false,
//                'error' => 'Failed to fetch data from tax service: ' . $e->getMessage()
//            ], 500);
//        }
//    }


}
