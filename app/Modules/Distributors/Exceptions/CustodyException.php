<?php

namespace App\Modules\Distributors\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class CustodyException extends Exception
{
    protected array $translations = [];

    public function __construct(array $translations = [], ?string $message = null)
    {
        $this->translations = $translations;
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
        return trans('errors.custody.'.$this->translationKey().'.message', $this->translations);
    }

    public function getTranslations(): array
    {
        return $this->translations;
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
