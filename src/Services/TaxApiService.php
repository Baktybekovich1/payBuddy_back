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
            number_format($data['ticketTotalSum'] / 100, 2, '.', ''),
            $products
        );

        return $answer;
    }

    public function getProducts(array $products): array
    {
        $answers = [];
        $a = 1;
        foreach ($products as $product) {
            $answers[] = new ProductDto(
                $a,
                $product['goodName'],
                number_format($product['goodPrice'] / 100, 2, '.', ''),
                $product['goodQuantity'],
                number_format($product['goodCost'] / 100, 2, '.', '')
            );
            $a++;
        }
        return $answers;
    }

}
