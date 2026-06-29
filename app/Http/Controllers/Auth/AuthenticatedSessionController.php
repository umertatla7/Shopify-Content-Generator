<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\ShopifyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request, ShopifyContext $shopifyContext): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The provided credentials do not match our records.']);
        }

        $request->session()->regenerate();

        ActivityLog::query()->create([
            'account_id' => $request->user()->current_account_id,
            'user_id' => $request->user()->id,
            'subject_type' => $request->user()->getMorphClass(),
            'subject_id' => $request->user()->id,
            'action' => 'auth.login',
            'description' => "{$request->user()->name} signed in.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->intended($shopifyContext->decorate(route('dashboard'), $request));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            ActivityLog::query()->create([
                'account_id' => $user->current_account_id,
                'user_id' => $user->id,
                'subject_type' => $user->getMorphClass(),
                'subject_id' => $user->id,
                'action' => 'auth.logout',
                'description' => "{$user->name} signed out.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
