<?php

namespace App\Services\PayrollIntegration;

use App\Services\PayrollIntegration\Contracts\PayrollServiceInterface;

abstract class BasePayrollConnector implements PayrollServiceInterface
{
    protected array $config;
    protected ?string $apiKey;
    protected ?string $apiSecret;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->apiKey = $config['api_key'] ?? null;
        $this->apiSecret = $config['api_secret'] ?? null;
    }

    abstract public function authenticate(): bool;
    abstract public function getAuthToken(): ?string;
}
