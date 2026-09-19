<?php

namespace App\Modules\Authentication\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class AuthenticationException extends Exception
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? $this->getLocalizedMessage());
    }

    /**
     * Stable machine-readable code. Never translated.
     */
    abstract protected function errorCode(): string;

    /**
     * Stable HTTP status. Never translated.
     */
    abstract protected function httpStatus(): int;

    /**
     * Locale-independent key used to resolve the localized message,
     * e.g. "auth.invalid_credentials" -> lang/{locale}/errors.php
     */
    abstract protected function translationKey(): string;

    public function getErrorCode(): string
    {
        return $this->errorCode();
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus();
    }

    public function getLocalizedMessage(): string
    {
        return trans('errors.'.$this->translationKey().'.message');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getLocalizedMessage(),
            'error_code' => $this->getErrorCode(),
        ], $this->getHttpStatus());
    }
}
