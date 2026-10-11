<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request)
    {
        if ($request->user()->two_factor_secret && time() - $request->session()->get('auth.password_confirmed_at', 0) > config('auth.password_timeout', 10800)) {
            $request->session()->put('url.intended', route('profile.edit'));

            return redirect()->route('password.confirm');
        }

        return view('profile.edit', ['user' => $request->user()]);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request)
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->tokens()->delete();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
