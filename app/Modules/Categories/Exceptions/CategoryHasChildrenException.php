<?php

namespace App\Modules\Categories\Exceptions;

class CategoryHasChildrenException extends CategoriesException
{
    protected function errorCode(): string
    {
        return 'CATEGORY_HAS_CHILDREN';
    }

    protected function httpStatus(): int
    {
        return 409;
    }

    protected function translationKey(): string
    {
        return 'category.has_children';
    }
}
