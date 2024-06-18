<?php

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\DomainObjects\UserDomainObject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class SetUserLocaleMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            /** @var UserDomainObject $user */
            $user = UserDomainObject::hydrateFromModel(Auth::user());
            App::setLocale($user->getLocale());
            App::setFallbackLocale(config('app.locale'));
        } elseif ($request->hasHeader('Accept-Language')) {
            App::setLocale($request->header('Accept-Language'));
            App::setFallbackLocale(config('app.locale'));
        }

        return $next($request);
    }
}
