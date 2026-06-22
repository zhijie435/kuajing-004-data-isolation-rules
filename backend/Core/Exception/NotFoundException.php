<?php

namespace App\Core\Exception;

class NotFoundException extends AppException
{
    protected int $httpCode = 404;
    protected string $errorCode = 'NOT_FOUND';

    public function __construct(string $message = '资源不存在', ?\Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
