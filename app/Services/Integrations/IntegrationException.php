<?php

namespace App\Services\Integrations;

use RuntimeException;

class IntegrationException extends RuntimeException
{
    public static function notConfigured(string $service): self
    {
        return new self("Интеграция «{$service}» не настроена: укажите ключ в админке (Настройки → Интеграции).");
    }
}
