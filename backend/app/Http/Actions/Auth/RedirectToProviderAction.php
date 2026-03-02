<?php

namespace HiEvents\Http\Actions\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class RedirectToProviderAction extends BaseAuthAction
{
    public function __invoke(string $provider, Request $request): \Symfony\Component\HttpFoundation\RedirectResponse|RedirectResponse
    {
        $provider = strtolower($provider);

        return Socialite::driver($provider)->stateless()->redirect();
    }
}
