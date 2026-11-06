<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function form()
    {
        return view('admin.auth.login', [
            'action' => route('super.login'),
            'heading' => config('platform.name'),
            'subheading' => 'Platform administration',
            'icon' => 'shield-lock',
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $credentials['email'] = strtolower($credentials['email']);

        if (! Auth::guard('web')->attempt($credentials + ['status' => 'active', 'is_super_admin' => true], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Those details do not match a platform administrator.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = Auth::guard('web')->user();
        $user->forceFill(['last_login_at' => now()])->save();
        DB::table('login_histories')->insert(['user_id' => $user->id, 'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500), 'created_at' => now()]);

        return redirect()->intended(route('super.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('super.login');
    }
}
