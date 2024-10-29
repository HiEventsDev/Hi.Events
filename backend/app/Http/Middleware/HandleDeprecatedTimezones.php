<?php

namespace HiEvents\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HandleDeprecatedTimezones
{
    public function handle(Request $request, Closure $next)
    {
        $timezoneMapping = config('timezones.deprecated');

        if ($request->has('timezone')) {
            $timezone = $request->input('timezone');

            if (array_key_exists($timezone, $timezoneMapping)) {
                $request->merge(['timezone' => $timezoneMapping[$timezone]]);
            } elseif (!in_array($timezone, timezone_identifiers_list(), true)) {
                Log::warning("Unexpected timezone received: $timezone");
            }
        }

        return $next($request);
    }
}
