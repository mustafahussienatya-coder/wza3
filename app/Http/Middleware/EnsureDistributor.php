<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDistributor
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isDistributor()) {
            return $this->errorResponse(
                __('errors.general.forbidden.message'),
                403,
                errorCode: 'FORBIDDEN'
            );
        }

        if (! $user->relationLoaded('distributor')) {
            $user->load('distributor');
        }

        if ($user->distributor === null) {
            return $this->errorResponse(
                __('errors.distributor.not_linked.message'),
                403,
                errorCode: 'DISTRIBUTOR_NOT_LINKED'
            );
        }

        if (! $user->distributor->isActive()) {
            return $this->errorResponse(
                __('errors.distributor.account_suspended.message'),
                403,
                errorCode: 'DISTRIBUTOR_SUSPENDED'
            );
        }

        return $next($request);
    }
}
