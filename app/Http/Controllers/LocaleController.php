<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The language switcher: remembers the choice for this session and, for a
 * signed-in member, on their account (so mail and notifications follow it).
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $locale = (string) $request->validate([
            'locale' => ['required', 'string', fn ($attribute, $value, $fail) => is_string($value) && SetLocale::supports($value) ? null : $fail(__('That language is not available.'))],
        ])['locale'];

        $request->session()->put('locale', $locale);

        $user = $request->user('web');

        if ($user instanceof User && $user->locale !== $locale) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return back();
    }
}
