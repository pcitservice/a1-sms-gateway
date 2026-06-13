<?php

use Illuminate\Support\Facades\Route;

// The web frontend is served by Next.js; this file exists so Laravel's
// router boots cleanly and Cashier's webhook ping can resolve. The single
// route below is a "is the API up" landing page for humans hitting the
// bare host.

Route::get('/', function () {
    return response()->json([
        'app'  => config('app.name'),
        'docs' => url('/api/documentation'),
        'time' => now()->toIso8601String(),
    ]);
});
