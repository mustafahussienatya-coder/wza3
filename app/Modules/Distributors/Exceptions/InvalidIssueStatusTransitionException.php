<?php

namespace App\Modules\Distributors\Exceptions;

class InvalidIssueStatusTransitionException extends CustodyException
{
    protected function errorCode(): string
    {
        return 'INVALID_ISSUE_STATUS_TRANSITION';
    }

    protected function httpStatus(): int
    {
        return 422;
    }

    protected function translationKey(): string
    {
        return 'invalid_status_transition';
    }
}
