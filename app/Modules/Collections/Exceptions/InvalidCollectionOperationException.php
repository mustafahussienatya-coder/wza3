<?php

namespace App\Modules\Collections\Exceptions;

class InvalidCollectionOperationException extends CollectionException
{
    protected function errorCode(): string
    {
        return 'INVALID_COLLECTION_OPERATION';
    }

    protected function httpStatus(): int
    {
        return 422;
    }

    protected function translationKey(): string
    {
        return 'invalid_operation';
    }
}
