<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function form()
    {
        return view('admin.auth.login', [
            'action' => route('admin.login'),
            'heading' => $this->currentStore()->name,
            'subheading' => 'Sign in to manage your store',
            'icon' => 'shop',
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $credentials['email'] = strtolower($credentials['email']);

        if (! Auth::guard('web')->attempt($credentials + ['status' => 'active'], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Those details do not match our records.'])->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        // Signing in on a store's domain only works for that store's staff (or a super admin).
        if (! $user->is_super_admin && ! $user->membershipFor($this->currentStore())) {
            Auth::guard('web')->logout();

            return back()->withErrors(['email' => 'This account does not have access to '.$this->currentStore()->name.'.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        DB::table('login_histories')->insert([
            'user_id' => $user->id, 'store_id' => $this->currentStore()->id, 'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500), 'created_at' => now(),
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
