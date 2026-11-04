<?php

namespace App\Services;

use RuntimeException;

class InsufficientFundsException extends RuntimeException
{
    public function __construct(public float $needed)
    {
        parent::__construct('Недостаточно средств на балансе: не хватает '.number_format($needed, 2, ',', ' ').' ₽');
    }
}
