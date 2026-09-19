<?php

namespace App\Modules\Settlements\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class SettlementException extends Exception
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? $this->getLocalizedMessage());
    }

    abstract protected function errorCode(): string;

    abstract protected function httpStatus(): int;

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
        return trans('errors.settlement.'.$this->translationKey().'.message');
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
