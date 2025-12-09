<?php

namespace App\Services;

class MbankQrService
{
    public function generateQrWithAmount(string $baseQrLink, float $amount): string
    {
        $parts = parse_url($baseQrLink);
        if (!isset($parts['fragment'])) {
            throw new \InvalidArgumentException('No QR data');
        }
        $emv = $parts['fragment'];

        /* 1. Убираем старое поле 54, если есть */
        $emv = preg_replace('/54\d+\d+/', '', $emv);

        /* 2. Разбиваем на теги в порядке */
        preg_match_all('/(\d{2})(\d{2})(.+?)(?=\d{4}|$)/', $emv, $matches, PREG_SET_ORDER);

        $tags = [];
        foreach ($matches as $m) {
            $tags[$m[1]] = $m[3];
        }

        /* 3. Добавляем поле 54 (сумма) */
        $amountStr = str_pad(number_format($amount, 2, '', ''), 6, '0', STR_PAD_LEFT);
        $tags['54'] = $amountStr;

        /* 4. Собираем строку в правильном порядке */
        $order = ['00','01','52','53','54','59','62']; // нужный порядок для mbank
        $out = '';
        foreach ($order as $tag) {
            if (isset($tags[$tag])) {
                $out .= $tag . str_pad((string)strlen($tags[$tag]), 2, '0', STR_PAD_LEFT) . $tags[$tag];
            }
        }

        /* 5. CRC-16/CCITT-FALSE для поля 63 */
        $crc = $this->crc16ccitt($out);
        $out .= '6304' . strtoupper(dechex($crc));

        return 'https://app.mbank.kg/qr/#' . $out;
    }

    private function crc16ccitt(string $data): int
    {
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x8000) ? ($crc << 1) ^ 0x1021 : $crc << 1;
            }
            $crc &= 0xFFFF;
        }
        return $crc;
    }
}
