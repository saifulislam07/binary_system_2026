<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Language of the member-facing site: the visitor's pick for this session,
 * else the member's saved choice, else business.default_locale (Bangla).
 * The admin panel is left alone (APP_LOCALE, English).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin', 'admin/*')) {
            app()->setLocale(self::resolve($request));
        }

        return $next($request);
    }

    public static function resolve(Request $request): string
    {
        $user = $request->user('web');
        $candidates = [
            $request->hasSession() ? $request->session()->get('locale') : null,
            $user instanceof User ? $user->locale : null,
            config('business.default_locale'),
        ];

        foreach ($candidates as $locale) {
            if (is_string($locale) && self::supports($locale)) {
                return $locale;
            }
        }

        return 'en';
    }

    public static function supports(string $locale): bool
    {
        return array_key_exists($locale, (array) config('business.locales'));
    }
}
