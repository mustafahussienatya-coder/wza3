<?php

namespace App\Modules\Products\Exceptions;

use Exception;

class InvalidBulkRowException extends Exception
{
    public function __construct(public readonly string $errorCode)
    {
        parent::__construct($errorCode);
    }
}
