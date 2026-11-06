<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/** Shopper accounts, per store. Sign in with phone number (or email) and password. */
class AccountController extends Controller
{
    public function loginForm()
    {
        return view('storefront.account.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['login' => 'required|string|max:150', 'password' => 'required|string']);
        $login = trim($data['login']);
        $field = str_contains($login, '@') ? 'email' : 'phone';
        $value = $field === 'phone' ? substr(preg_replace('/\D/', '', $login), -10) : strtolower($login);

        $customer = Customer::where($field, $value)->whereNotNull('password')->where('status', 'active')->first();

        if (! $customer || ! Auth::guard('customer')->validate(['id' => $customer->id, 'password' => $data['password']])) {
            return back()->withInput($request->only('login'))->with('error', 'Those details do not match an account.');
        }

        Auth::guard('customer')->login($customer, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('shop.account'));
    }

    public function registerForm()
    {
        return view('storefront.account.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => ['required', 'string', 'regex:/^(\+?977[- ]?)?9[678]\d{8}$/'],
            'email' => 'nullable|email|max:150',
            'password' => ['required', 'confirmed', Password::min(8)],
        ], ['phone.regex' => 'Enter a 10-digit Nepali mobile number.']);

        $phone = substr(preg_replace('/\D/', '', $data['phone']), -10);
        $customer = Customer::where('phone', $phone)->first();

        if ($customer?->hasAccount()) {
            return back()->withInput()->with('error', 'An account with this phone number already exists. Please sign in.');
        }

        // A guest who ordered before keeps their order history when they register.
        $customer ??= new Customer(['phone' => $phone]);
        $customer->fill(['name' => $data['name'], 'email' => $data['email'] ? strtolower($data['email']) : $customer->email, 'password' => $data['password']]);
        $customer->save();

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->route('shop.account')->with('status', 'Welcome, '.$customer->name.'!');
    }

    public function index()
    {
        $customer = auth('customer')->user();

        return view('storefront.account.index', [
            'customer' => $customer,
            'orders' => $customer->orders()->with('items')->paginate(10),
            'address' => $customer->addresses()->orderByDesc('is_default')->first(),
        ]);
    }

    public function update(Request $request)
    {
        $customer = auth('customer')->user();
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'nullable|email|max:150',
            'city' => 'nullable|string|max:100',
            'area' => 'nullable|string|max:150',
            'address_line' => 'nullable|string|max:250',
            'landmark' => 'nullable|string|max:250',
        ]);

        $customer->update(['name' => $data['name'], 'email' => $data['email']]);

        if (! empty($data['city']) && ! empty($data['address_line'])) {
            $customer->addresses()->updateOrCreate(['is_default' => true], [
                'name' => $customer->name, 'phone' => $customer->phone, 'city' => $data['city'],
                'area' => $data['area'], 'address_line' => $data['address_line'], 'landmark' => $data['landmark'],
            ]);
        }

        return back()->with('status', 'Your details are saved.');
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('shop.home');
    }
}
