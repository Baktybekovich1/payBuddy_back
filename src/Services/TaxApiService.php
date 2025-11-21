<?php

namespace App\Services;

use App\Dto\LinkAnswerDto;
use App\Dto\ProductDto;

class TaxApiService
{
    public function __construct()
    {
    }

    public function TaxApi($data)
    {
        $dateTime = new \DateTime($data['dateTime']);

        $date = $dateTime->format('Y-m-d');     // 2025-11-21
        $time = $dateTime->format('H:i:s');
        $products = $this->getProducts($data['items']);
        $answer = new LinkAnswerDto(
            $data['id'],
            $date,
            $time,
            $data['crData']['cashierName'],
            $data['crData']['locationName'],
            $data['crData']['locationAddress'],
            $data['ticketTotalSum'],
            $products
        );

        return $date;
    }

    public function getProducts(array $products): array
    {
        $answers = [];
        $a = 1;
        foreach ($products as $product) {
            $answers[] = new ProductDto(
                $a,
                $product['goodName'],
                $product['goodPrice'],
                $product['goodQuantity'],
                $product['goodCost'],
            );
            $a++;
        }
        return $answers;
    }

}
