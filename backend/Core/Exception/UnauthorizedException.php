<?php

namespace App\Core\Exception;

class UnauthorizedException extends AppException
{
    protected int $httpCode = 401;
    protected string $errorCode = 'UNAUTHORIZED';

    public function __construct(string $message = '未登录或登录已过期', ?\Throwable $previous = null)
    {
        parent::__construct($message, 401, $previous);
    }
}
