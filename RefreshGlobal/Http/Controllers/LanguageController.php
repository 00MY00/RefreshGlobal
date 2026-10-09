<?php

namespace Modules\RefreshGlobal\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Language switch of the "All mailboxes" page. There is one language choice only: the one of the user's FreeScout
 * profile, which FreeScout, Refresh (app()->getLocale(), RefreshServiceProvider.php:41, 78) and this module all
 * follow. The switch saves it exactly like the profile page does (users.locale, then the session value read by the
 * Localize middleware: app/Http/Controllers/UsersController.php:243-245, app/Http/Middleware/Localize.php:22).
 */
class LanguageController extends Controller
{
    /** Languages offered by FreeScout (config/app.php "locales" + translations added on its Translate page). */
    public static function locales()
    {
        $locales = (array) config('app.locales', ['en']);
        if (method_exists(\App\Misc\Helper::class, 'getCustomLocales')) {
            $locales = array_merge($locales, (array) \App\Misc\Helper::getCustomLocales());
        }

        return array_values(array_unique($locales));
    }

    public static function name($locale)
    {
        $data = method_exists(\App\Misc\Helper::class, 'getLocaleData') ? \App\Misc\Helper::getLocaleData($locale) : null;

        return is_array($data) && !empty($data['name']) ? $data['name'] : $locale;
    }

    public function update(Request $request)
    {
        return $this->safely(function () use ($request) {
            $this->validate($request, ['locale' => 'required|string|in:'.implode(',', self::locales())]);
            $user = auth()->user();
            if (!$user->can('update', $user)) {
                abort(403);
            }
            $locale = (string) $request->input('locale');
            $user->locale = $locale;
            $user->save();
            session()->put('user_locale', $locale);
            \Helper::setLocale($locale);

            // back to the page the switch was used on (same site only)
            $back = url()->previous();
            if (!$back || strpos($back, url('/')) !== 0) {
                $back = route('refreshglobal.tickets');
            }

            return redirect($back)->with('flash_success', __('refreshglobal::messages.language_changed', ['language' => self::name($locale)]));
        });
    }
}
