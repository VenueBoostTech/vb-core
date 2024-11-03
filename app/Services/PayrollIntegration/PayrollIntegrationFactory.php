<?php

namespace App\Services\PayrollIntegration;

use App\Services\PayrollIntegration\Contracts\PayrollServiceInterface;
use App\Services\PayrollIntegration\Providers\{
    ADPConnector,
    WorkdayConnector,
    GustoConnector,
    PaychexConnector,
    QuickBooksConnector
};

class PayrollIntegrationFactory
{
    public static function create(string $provider, array $config = []): PayrollServiceInterface
    {
        return match ($provider) {
            'adp' => new ADPConnector($config),
            'workday' => new WorkdayConnector($config),
            'gusto' => new GustoConnector($config),
            'paychex' => new PaychexConnector($config),
            'quickbooks' => new QuickBooksConnector($config),
            default => throw new \InvalidArgumentException("Unsupported provider: {$provider}")
        };
    }
}
