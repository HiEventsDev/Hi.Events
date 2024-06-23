<?php

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Services\Application\Locale\LocaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class SetUserLocaleMiddleware
{
    public function __construct(private readonly LocaleService $localeService)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $this->setLocale($request);
        App::setFallbackLocale(config('app.locale'));

        return $next($request);
    }

    protected function setLocale(Request $request): void
    {
        if ($this->setLocaleFromCookie($request)) {
            return;
        }

        if ($this->setLocaleFromUser()) {
            return;
        }

        $this->setLocaleFromAcceptLanguage($request);
    }

    protected function setLocaleFromCookie(Request $request): bool
    {
        if ($locale = $request->cookie('locale')) {
            App::setLocale($this->localeService->getLocaleOrDefault($locale));
            return true;
        }

        return false;
    }

    protected function setLocaleFromUser(): bool
    {
        if (Auth::check()) {
            /** @var UserDomainObject $user */
            $user = UserDomainObject::hydrateFromModel(Auth::user());
            App::setLocale($user->getLocale());
            return true;
        }

        return false;
    }

    protected function setLocaleFromAcceptLanguage(Request $request): bool
    {
        if ($request->hasHeader('Accept-Language')) {
            App::setLocale($this->localeService->getLocaleOrDefault($request->getPreferredLanguage()));
            return true;
        }

        return false;
    }
}
