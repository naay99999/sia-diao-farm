<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReturnTo
{
    /**
     * Send the user back to the records page they came from, keeping its filters;
     * any other (or foreign) referer keeps the default redirect.
     */
    public static function recordsOr(Request $request, RedirectResponse $default): RedirectResponse
    {
        $referer = $request->headers->get('referer');

        if (! is_string($referer) || $referer === '') {
            return $default;
        }

        $parts = parse_url($referer);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $default;
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if ($origin !== $request->getSchemeAndHttpHost() || ! str_starts_with($parts['path'] ?? '', '/records/')) {
            return $default;
        }

        return redirect()->to($referer);
    }
}
