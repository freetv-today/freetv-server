<?php

namespace FreeTV\Admin\Publication;

use RuntimeException;

class PublicationException extends RuntimeException
{
    public function __construct(string $message, private int $httpStatus = 500)
    {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
