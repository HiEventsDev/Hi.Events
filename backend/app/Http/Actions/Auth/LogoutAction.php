<?php

namespace HiEvents\Http\Actions\Auth;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class LogoutAction
{
    public function __invoke(): Response
    {
        Auth::logout();

        $cookie = Cookie::forget('token');

        return (new Response([
            'message' => __('Logout Successful')
        ]))
            ->withCookie($cookie);
    }
}
