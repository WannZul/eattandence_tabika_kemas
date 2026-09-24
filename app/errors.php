<?php
declare(strict_types=1);

final class UserFacingException extends RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus = 400)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus >= 400 && $this->httpStatus <= 599 ? $this->httpStatus : 400;
    }
}
