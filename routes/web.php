<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

use Feeder\Core\Helpers\Test;

// ----------------- Test Route for dropshipping-core package ----------------------------------------------
// Route::get('package-test', function () {
//     return Test::hello();
// });
// ---------------------------------------------------------------------------------------------------------

// ----------------- Test Route for template ---------------------------------------------------------------
Route::get('/test', function () {
    return view('test_index');
});
// ---------------------------------------------------------------------------------------------------------

// ----------------- Main Routes ---------------------------------------------------------------------------
Route::get('/main/dashboard', function () {
    return view('pages.main.dashboard');
});
// ----------------- Main Routes ---------------------------------------------------------------------------
Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('pages.main.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/files/{uuid}/thumbnail/{size?}', [\App\Http\Controllers\FileProxyController::class, 'thumbnail'])
        ->where([
            'uuid' => '[A-Za-z0-9]+',
            'size' => 'sm|md|lg',
        ])
        ->name('files.thumbnail');

    Route::get('/files/{uuid}/view', [\App\Http\Controllers\FileProxyController::class, 'view'])
        ->where('uuid', '[A-Za-z0-9]+')
        ->name('files.view');

    Route::get('/products', [\App\Http\Controllers\Product\ProductController::class, 'index'])->name('products');
    Route::get('/products/index', [\App\Http\Controllers\Product\ProductController::class, 'index'])->name('products.index');
    Route::get('/products/list', [\App\Http\Controllers\Product\ProductController::class, 'index'])->name('products.list');
    Route::get('/products/create', [\App\Http\Controllers\Product\ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [\App\Http\Controllers\Product\ProductController::class, 'store'])->name('products.store');

    Route::get('/products/{product}', [\App\Http\Controllers\Product\ProductController::class, 'show'])->name('products.show');
    Route::get('/products/{product}/details', [\App\Http\Controllers\Product\ProductController::class, 'show'])->name('products.details');
    Route::get('/products/{product}/edit', [\App\Http\Controllers\Product\ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [\App\Http\Controllers\Product\ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [\App\Http\Controllers\Product\ProductController::class, 'destroy'])->name('products.destroy');
    Route::post('/products/{product}/deactivate', [\App\Http\Controllers\Product\ProductController::class, 'deactivate'])->name('products.deactivate');
    Route::post('/products/{product}/activate', [\App\Http\Controllers\Product\ProductController::class, 'activate'])->name('products.activate');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/registration.php';
require __DIR__ . '/auth.php';
