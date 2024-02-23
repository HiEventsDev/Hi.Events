<?php

namespace TicketKitten\Http\Actions\Auth;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class LogoutAction
{
    public function __invoke(): Response
    {
        Auth::logout();

        return new Response([
            'message' => __('Logout Successful')
        ]);
    }
}
