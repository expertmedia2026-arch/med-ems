<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/** Maps the <x-app-layout> shell to the authenticated staff layout. */
class AppLayout extends Component
{
    /** Render the shared staff navigation, branding, and page slot. */
    public function render(): View
    {
        return view('layouts.app');
    }
}
