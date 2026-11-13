<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('checkin_pin') === self::fingerprint()) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'PIN required'], 401)
            : redirect()->route('login');
    }

    /** Changing CHECKIN_PIN in .env signs every device out. */
    public static function fingerprint(): string
    {
        return hash('sha256', config('checkin.pin').'|'.config('app.key'));
    }
}