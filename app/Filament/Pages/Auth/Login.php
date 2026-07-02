<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    public function getSubheading(): string | Htmlable | null
    {
        return 'Local control for your Petkit devices';
    }
}
