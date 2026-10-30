<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/** Maps the <x-guest-layout> shell to unauthenticated account pages. */
class GuestLayout extends Component
{
    /** Render the guest-facing shell used by login and account recovery. */
    public function render(): View
    {
        return view('layouts.guest');
    }
}
