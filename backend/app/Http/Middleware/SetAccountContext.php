<?php

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\TransientToken;

class SetAccountContext
{

    public function handle($request, Closure $next)
    {
        if (Auth::check()) {
            if (Auth::user()->currentAccessToken()) {
                if (Auth::user()->currentAccessToken() instanceof TransientToken) {
                    // assume logged in
                    $accountId = auth()->guard('api')->payload()->get('account_id');

                    if ($accountId) {
                        User::setCurrentAccountId($accountId);
                    }
                } else {
                    User::setCurrentAccountId(Auth::user()->currentAccessToken()->account_id);
                }
            } else {
                $accountId = Auth::payload()->get('account_id');

                if ($accountId) {
                    User::setCurrentAccountId($accountId);
                }
            }
        }

        return $next($request);
    }
}
