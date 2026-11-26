<?php

namespace App\Services;

/**
 * Writes peso amounts in words for receipts, e.g. 1550.50 → "One thousand five hundred fifty pesos and 50/100".
 */
class PesoWords
{
    private const ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
        'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    private const TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    private const SCALES = [1_000_000_000 => 'billion', 1_000_000 => 'million', 1_000 => 'thousand'];

    public static function format(float $amount): string
    {
        $pesos = (int) floor(round($amount, 2));
        $centavos = (int) round(($amount - $pesos) * 100);
        $words = $pesos === 0 ? 'zero' : self::words($pesos);

        return ucfirst($words).' '.($pesos === 1 ? 'peso' : 'pesos').($centavos > 0 ? sprintf(' and %02d/100', $centavos) : ' only');
    }

    private static function words(int $number): string
    {
        foreach (self::SCALES as $value => $name) {
            if ($number >= $value) {
                $rest = $number % $value;

                return self::words(intdiv($number, $value))." {$name}".($rest ? ' '.self::words($rest) : '');
            }
        }

        if ($number >= 100) {
            $rest = $number % 100;

            return self::ONES[intdiv($number, 100)].' hundred'.($rest ? ' '.self::words($rest) : '');
        }

        if ($number >= 20) {
            return self::TENS[intdiv($number, 10)].($number % 10 ? '-'.self::ONES[$number % 10] : '');
        }

        return self::ONES[$number];
    }
}
