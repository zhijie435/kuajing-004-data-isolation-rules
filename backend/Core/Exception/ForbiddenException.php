<?php

namespace App\Core\Exception;

class ForbiddenException extends AppException
{
    protected int $httpCode = 403;
    protected string $errorCode = 'FORBIDDEN';

    public function __construct(string $message = '无权访问该资源', ?\Throwable $previous = null)
    {
        parent::__construct($message, 403, $previous);
    }
}
