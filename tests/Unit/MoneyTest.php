<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_amount_in_words_for_invoice(): void
    {
        $this->assertSame('Двенадцать тысяч пятьсот рублей 00 копеек', Money::words(12500));
        $this->assertSame('Одна тысяча двадцать один рубль 50 копеек', Money::words(1021.5));
        $this->assertSame('Два миллиона три тысячи два рубля 01 копейка', Money::words(2003002.01));
    }

    public function test_plural(): void
    {
        $this->assertSame('кликов', Money::plural(11, 'клик', 'клика', 'кликов'));
        $this->assertSame('клика', Money::plural(22, 'клик', 'клика', 'кликов'));
        $this->assertSame('клик', Money::plural(101, 'клик', 'клика', 'кликов'));
    }
}
