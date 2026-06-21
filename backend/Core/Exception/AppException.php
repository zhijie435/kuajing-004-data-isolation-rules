<?php

namespace App\Core\Exception;

class AppException extends \RuntimeException
{
    protected int $httpCode = 400;
    protected string $errorCode = 'APP_ERROR';
    protected array $context = [];

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'code' => $this->httpCode,
            'error_code' => $this->errorCode,
            'message' => $this->getMessage(),
            'context' => $this->context,
        ];
    }
}
