<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\MeetupController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

// Public pages
Route::get('/', [BookController::class, 'index'])->name('home');
Route::get('/books/{listing}', [BookController::class, 'show'])->name('books.show');

// Guests only
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Logged-in users only
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Module A: Scan & Listing
    Route::get('/sell', [ListingController::class, 'create'])->name('listings.create');
    Route::post('/sell', [ListingController::class, 'store'])->name('listings.store');
    // Calls Google Books / Open Library: throttled to protect the API quota.
    Route::get('/listings/lookup', [ListingController::class, 'lookup'])
        ->middleware('throttle:20,1')->name('listings.lookup');
    // Calls OCR.space (+ Google Books / Open Library).
    Route::post('/listings/scan', [ListingController::class, 'scan'])
        ->middleware('throttle:10,1')->name('listings.scan');
    // Preview of a temporary scan (stored outside public/ until the listing is posted).
    Route::get('/listings/scan-photo/{name}', [ListingController::class, 'scanPhoto'])
        ->where('name', '[A-Za-z0-9-]+\.(jpg|png|webp)')->name('listings.scan-photo');
    // Reset button: delete the seller's own temporary scan.
    Route::delete('/listings/scan-photo/{name}', [ListingController::class, 'discardScan'])
        ->where('name', '[A-Za-z0-9-]+\.(jpg|png|webp)')->name('listings.scan-photo.discard');
    // Fuller details (edition, publisher) of the book the seller picked.
    Route::get('/listings/book-details', [ListingController::class, 'bookDetails'])
        ->middleware('throttle:30,1')->name('listings.book-details');

    // Module B: Catalogue (seller side)
    Route::get('/my-listings', [ListingController::class, 'mine'])->name('listings.mine');
    Route::get('/listings/{listing}/edit', [ListingController::class, 'edit'])->name('listings.edit');
    Route::put('/listings/{listing}', [ListingController::class, 'update'])->name('listings.update');
    Route::delete('/listings/{listing}', [ListingController::class, 'destroy'])->name('listings.destroy');

    // Module C: Orders (cash at the meetup)
    Route::get('/books/{listing}/book', [OrderController::class, 'create'])->name('orders.create');
    Route::post('/books/{listing}/book', [OrderController::class, 'store'])->name('orders.store');
    // Calls Nominatim + OSRM for the booking map.
    Route::get('/meetups/suggest', [MeetupController::class, 'suggest'])
        ->middleware('throttle:20,1')->name('meetups.suggest');
    Route::get('/meetups/route', [MeetupController::class, 'route'])
        ->middleware('throttle:30,1')->name('meetups.route');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/complete', [OrderController::class, 'complete'])->name('orders.complete');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/rate', [OrderController::class, 'rate'])->name('orders.rate');
});

// Admin area (only users with is_admin; create the first admin with: php artisan unibook:make-admin <email>)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/edit', [Admin\UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{user}/ban', [Admin\UserController::class, 'ban'])->name('users.ban');
    Route::post('/users/{user}/unban', [Admin\UserController::class, 'unban'])->name('users.unban');

    Route::get('/listings', [Admin\ListingController::class, 'index'])->name('listings.index');
    Route::post('/listings/{listing}/hide', [Admin\ListingController::class, 'hide'])->name('listings.hide');
    Route::post('/listings/{listing}/unhide', [Admin\ListingController::class, 'unhide'])->name('listings.unhide');
    Route::delete('/listings/{listing}', [Admin\ListingController::class, 'destroy'])->name('listings.destroy');

    Route::get('/orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders/{order}/cancel', [Admin\OrderController::class, 'cancel'])->name('orders.cancel');

    Route::get('/books', [Admin\BookController::class, 'index'])->name('books.index');
    Route::get('/books/{book}/edit', [Admin\BookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}', [Admin\BookController::class, 'update'])->name('books.update');
    Route::delete('/books/{book}', [Admin\BookController::class, 'destroy'])->name('books.destroy');
});
