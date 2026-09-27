<?php

namespace App\Domains\Package\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class ArtifactRejectedException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
    ) {
        parent::__construct($message);
    }

    public static function invalid(string $message): self
    {
        return new self($message);
    }

    public static function conflict(string $message): self
    {
        return new self($message, Response::HTTP_CONFLICT);
    }

    public static function notFound(string $message): self
    {
        return new self($message, Response::HTTP_NOT_FOUND);
    }
}
