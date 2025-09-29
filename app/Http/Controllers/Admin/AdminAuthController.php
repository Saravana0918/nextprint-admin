<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function showLoginForm()
    {
        // return the blade view you used (you showed admin-login blade in screenshots)
        return view('auth.admin-login'); // adjust if your view path is different
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required','email'],
            'password' => ['required','string'],
        ]);

        // Attempt login using default guard (web). If you have admin-specific guard,
        // change Auth::attempt accordingly.
        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();
            // redirect to admin dashboard or where you want
            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors(['email' => 'Credentials not matched'])->withInput();
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
