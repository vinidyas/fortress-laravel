<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $portalDomain = config('app.portal_domain');
        $host = $request->getHost();

        if ($portalDomain && $host === $portalDomain) {
            return $this->handlePortalLogin($request);
        }

        $credentials = $request->validated();

        if (! Auth::attempt(
            ['username' => $credentials['username'], 'password' => $credentials['password']],
            $request->boolean('remember')
        )) {
            return back()->withErrors([
                'username' => __('auth.failed'),
            ])->onlyInput('username');
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if ($user) {
            $user->forceFill(['last_login_at' => now()])->save();
        }

        $this->auditLogger->record('auth.login', $user);

        $redirectTo = route('dashboard');

        if ($user && method_exists($user, 'hasTenantAccess') && $user->hasTenantAccess()) {
            $hasAdminAccess = $user->can('admin.access');
            if ($host === $portalDomain || ! $hasAdminAccess) {
                if (Route::has('portal.dashboard')) {
                    $redirectTo = route('portal.dashboard');
                }
            }
        }

        return redirect()->intended($redirectTo);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->auditLogger->record('auth.logout', $user);

        return redirect()->route('login');
    }

    private function handlePortalLogin(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $email = strtolower($credentials['email'] ?? '');
        $remember = $request->boolean('remember');

        $user = User::query()
            ->whereNotNull('email')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user || ! $user->hasTenantAccess()) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! Auth::attempt(['username' => $user->username, 'password' => $credentials['password']], $remember)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        $this->auditLogger->record('auth.login', $user);

        $redirect = Route::has('portal.dashboard')
            ? route('portal.dashboard')
            : route('dashboard');

        return redirect()->intended($redirect);
    }
}
