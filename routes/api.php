<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\MeetupController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

/*
| UniBook REST API (prefix /api).
| Gateway: ForceJsonResponse -> Sanctum token check -> throttle -> controller.
| Every response: { "success": bool, "message": string, "data": ... }
| All integrations are request-response: UniBook calls the external APIs, nothing calls UniBook.
*/

// Public
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('api.login');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Module A: Scan & Listing (member A)
    Route::post('/listings/scan', [ListingController::class, 'scan'])->middleware('throttle:10,1');
    Route::post('/listings', [ListingController::class, 'store']);

    // Module B: Catalogue (member B)
    Route::get('/listings', [ListingController::class, 'index']);
    Route::get('/listings/{listing}', [ListingController::class, 'show']);
    Route::put('/listings/{listing}', [ListingController::class, 'update']);
    Route::delete('/listings/{listing}', [ListingController::class, 'destroy']);

    // Module C: Orders (member C). Payment is cash at the meetup.
    Route::post('/orders', [OrderController::class, 'store']);
    Route::put('/orders/{order}', [OrderController::class, 'update']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/meetups', [MeetupController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/meetups/suggest', [MeetupController::class, 'suggest'])->middleware('throttle:20,1');
    Route::get('/meetups/{meetup}/route', [MeetupController::class, 'route'])->middleware('throttle:30,1');
});
