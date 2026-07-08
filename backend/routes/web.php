<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/*
 * Serve the built React SPA for every non-API route so client-side
 * routing (deep links like /customers, /inbox) works on refresh.
 * The compiled index.html lives in resources/spa.html and its hashed
 * assets are published to public/assets by the build script.
 */
Route::get('/{any}', function () {
    $spa = resource_path('spa.html');

    abort_unless(File::exists($spa), 404, 'Run the frontend build first (npm run build).');

    return response(File::get($spa))->header('Content-Type', 'text/html');
})->where('any', '^(?!api|up|storage|sanctum).*$');
