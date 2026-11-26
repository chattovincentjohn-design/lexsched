<?php

namespace App\Http\Controllers;

use App\Models\Lawyer;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('home', [
            'services' => Service::query()->active()->orderBy('id')->get(),
            'lawyers' => Lawyer::query()->bookable()->with(['user', 'services'])->get(),
        ]);
    }

    /**
     * Sends a signed-in user to the right home page for their role.
     */
    public function dashboard(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->role->homeRoute());
    }
}
