<?php

namespace App\Core\Exception;

class ValidationException extends AppException
{
    protected int $httpCode = 400;
    protected string $errorCode = 'VALIDATION_ERROR';

    public function __construct(string $message = '参数校验失败', ?\Throwable $previous = null)
    {
        parent::__construct($message, 400, $previous);
    }
}
