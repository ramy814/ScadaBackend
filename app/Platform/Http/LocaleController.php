<?php

declare(strict_types=1);

namespace App\Platform\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $locale = in_array($request->input('locale'), config('platform.locales'), true)
            ? $request->input('locale')
            : 'ar';

        return back()->withCookie(cookie()->forever('locale', $locale, httpOnly: false));
    }
}
