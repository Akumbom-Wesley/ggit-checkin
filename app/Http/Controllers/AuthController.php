<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RequirePin;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function show(Request $request)
    {
        if ($request->session()->get('checkin_pin') === RequirePin::fingerprint()) {
            return redirect()->route('checkin');
        }

        return view('login');
    }

    public function login(Request $request)
    {
        $request->validate(['pin' => ['required', 'string']]);

        if (! hash_equals((string) config('checkin.pin'), trim($request->input('pin')))) {
            return back()->withErrors(['pin' => 'Wrong PIN. Try again.']);
        }

        $request->session()->regenerate();
        $request->session()->put('checkin_pin', RequirePin::fingerprint());

        return redirect()->route('checkin');
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}