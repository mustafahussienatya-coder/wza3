<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            $request->user()->currentAccessToken()?->delete();

            return $this->errorResponse(
                __('auth_messages.account_deactivated'),
                403,
                errorCode: 'ACCOUNT_DEACTIVATED'
            );
        }

        return $next($request);
    }
}
