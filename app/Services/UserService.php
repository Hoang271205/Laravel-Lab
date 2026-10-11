<?php

namespace App\Services;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class UserService
{
    public static function getUserIdFromSession(): string
    {
        if (Session::exists('user_id')) {
            return Session::get('user_id');
        }

        $user_id = Str::uuid()->toString();

        Session::put('user_id', $user_id);

        return $user_id;
    }
}
