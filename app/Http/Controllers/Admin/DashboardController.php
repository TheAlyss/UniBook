<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;

/** Admin home: counts and the latest sign-ups and listings. */
class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('admin.dashboard', [
            'users' => [
                'total' => User::count(),
                'admins' => User::where('is_admin', true)->count(),
                'banned' => User::whereNotNull('banned_at')->count(),
            ],
            'listings' => Listing::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'hiddenListings' => Listing::whereNotNull('hidden_at')->count(),
            'orders' => Order::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'books' => Book::count(),
            'latestUsers' => User::latest()->limit(5)->get(),
            'latestListings' => Listing::with('user')->latest()->limit(5)->get(),
        ]);
    }
}
