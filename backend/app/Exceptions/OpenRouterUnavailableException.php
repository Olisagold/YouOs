<?php

namespace App\Exceptions;

use RuntimeException;

class OpenRouterUnavailableException extends RuntimeException
{
    public function __construct(
        string $message = 'OpenRouter service is unavailable.',
        private readonly ?int $statusCode = null,
        private readonly ?string $responseBody = null
    ) {
        parent::__construct($message);
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    public function responseBody(): ?string
    {
        return $this->responseBody;
    }
}
