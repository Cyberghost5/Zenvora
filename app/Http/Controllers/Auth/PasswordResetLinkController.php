<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        // Deliberately the same response either way. Telling an anonymous
        // visitor whether an email is registered hands them a way to enumerate
        // accounts on a platform that holds money.
        return back()->with('status', match ($status) {
            Password::RESET_THROTTLED => 'A reset link was sent recently. Please check your inbox, or try again in a minute.',
            default => 'If that email is registered, a password reset link is on its way.',
        });
    }
}
