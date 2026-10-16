<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetPanelLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->cookie('hivepanel_locale', config('app.locale', 'en'));
        app()->setLocale(in_array($locale, ['en', 'de'], true) ? $locale : 'en');

        return $next($request);
    }
}
