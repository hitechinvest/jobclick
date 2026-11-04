<?php

namespace App\Support;

class Money
{
    public static function format(float|int|string|null $value, bool $withKopecks = false): string
    {
        $value = (float) $value;
        $decimals = $withKopecks || fmod($value, 1.0) !== 0.0 ? 2 : 0;

        return number_format($value, $decimals, ',', "\u{00A0}")."\u{00A0}₽";
    }

    public static function number(float|int|null $value): string
    {
        return number_format((float) $value, 0, ',', "\u{00A0}");
    }

    /** Сумма прописью для счёта: «Три тысячи рублей 00 копеек». */
    public static function words(float $amount): string
    {
        $rub = (int) floor($amount);
        $kop = (int) round(($amount - $rub) * 100);
        $words = self::numberToWords($rub);

        return mb_strtoupper(mb_substr($words, 0, 1)).mb_substr($words, 1).' '.self::plural($rub, 'рубль', 'рубля', 'рублей')
            .' '.str_pad((string) $kop, 2, '0', STR_PAD_LEFT).' '.self::plural($kop, 'копейка', 'копейки', 'копеек');
    }

    public static function plural(int $n, string $one, string $few, string $many): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) {
            return $many;
        }
        if ($n1 > 1 && $n1 < 5) {
            return $few;
        }
        if ($n1 === 1) {
            return $one;
        }

        return $many;
    }

    protected static function numberToWords(int $n): string
    {
        if ($n === 0) {
            return 'ноль';
        }
        $units = [['', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'], ['', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять']];
        $teens = ['десять', 'одиннадцать', 'двенадцать', 'тринадцать', 'четырнадцать', 'пятнадцать', 'шестнадцать', 'семнадцать', 'восемнадцать', 'девятнадцать'];
        $tens = ['', '', 'двадцать', 'тридцать', 'сорок', 'пятьдесят', 'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто'];
        $hundreds = ['', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот', 'шестьсот', 'семьсот', 'восемьсот', 'девятьсот'];
        $groups = [[null, null, null, 0], ['тысяча', 'тысячи', 'тысяч', 1], ['миллион', 'миллиона', 'миллионов', 0], ['миллиард', 'миллиарда', 'миллиардов', 0]];

        $parts = [];
        $i = 0;
        while ($n > 0) {
            $chunk = $n % 1000;
            if ($chunk > 0) {
                $words = [];
                $words[] = $hundreds[intdiv($chunk, 100)];
                $rest = $chunk % 100;
                if ($rest >= 10 && $rest < 20) {
                    $words[] = $teens[$rest - 10];
                } else {
                    $words[] = $tens[intdiv($rest, 10)];
                    $words[] = $units[$groups[$i][3]][$rest % 10];
                }
                if ($i > 0) {
                    $words[] = self::plural($chunk, $groups[$i][0], $groups[$i][1], $groups[$i][2]);
                }
                array_unshift($parts, implode(' ', array_filter($words)));
            }
            $n = intdiv($n, 1000);
            $i++;
        }

        return implode(' ', $parts);
    }
}
