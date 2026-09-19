<?php

namespace App\Modules\Categories\Exceptions;

class CategoryCannotDeleteException extends CategoriesException
{
    protected function errorCode(): string
    {
        return 'CATEGORY_CANNOT_DELETE';
    }

    protected function httpStatus(): int
    {
        return 409;
    }

    protected function translationKey(): string
    {
        return 'category.cannot_delete';
    }
}
