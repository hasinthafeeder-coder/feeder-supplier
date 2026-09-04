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

    Route::get('/products', [\App\Http\Controllers\Product\ProductController::class, 'index'])
        ->middleware('permission:products.view')
        ->name('products');
    Route::get('/products/index', [\App\Http\Controllers\Product\ProductController::class, 'index'])
        ->middleware('permission:products.view')
        ->name('products.index');
    Route::get('/products/list', [\App\Http\Controllers\Product\ProductController::class, 'index'])
        ->middleware('permission:products.view')
        ->name('products.list');
    Route::get('/products/create', [\App\Http\Controllers\Product\ProductController::class, 'create'])
        ->middleware('permission:products.create')
        ->name('products.create');
    Route::post('/products', [\App\Http\Controllers\Product\ProductController::class, 'store'])
        ->middleware('permission:products.create')
        ->name('products.store');

    Route::get('/products/{product}', [\App\Http\Controllers\Product\ProductController::class, 'show'])
        ->middleware('permission:products.view')
        ->name('products.show');
    Route::get('/products/{product}/details', [\App\Http\Controllers\Product\ProductController::class, 'show'])
        ->middleware('permission:products.view')
        ->name('products.details');
    Route::get('/products/{product}/edit', [\App\Http\Controllers\Product\ProductController::class, 'edit'])
        ->middleware('permission:products.update')
        ->name('products.edit');
    Route::put('/products/{product}', [\App\Http\Controllers\Product\ProductController::class, 'update'])
        ->middleware('permission:products.update')
        ->name('products.update');
    Route::delete('/products/{product}', [\App\Http\Controllers\Product\ProductController::class, 'destroy'])
        ->middleware('permission:products.delete')
        ->name('products.destroy');
    Route::post('/products/{product}/deactivate', [\App\Http\Controllers\Product\ProductController::class, 'deactivate'])
        ->middleware('permission:products.update')
        ->name('products.deactivate');
    Route::post('/products/{product}/activate', [\App\Http\Controllers\Product\ProductController::class, 'activate'])
        ->middleware('permission:products.update')
        ->name('products.activate');

    Route::get('/grns', [\App\Http\Controllers\Grn\GoodsReceivedNoteController::class, 'index'])
        ->middleware('permission:grns.view')
        ->name('grns.index');
    Route::get('/grns/create', [\App\Http\Controllers\Grn\GoodsReceivedNoteController::class, 'create'])
        ->middleware('permission:grns.create')
        ->name('grns.create');
    Route::post('/grns', [\App\Http\Controllers\Grn\GoodsReceivedNoteController::class, 'store'])
        ->middleware('permission:grns.create')
        ->name('grns.store');
    Route::get('/grns/{grn}', [\App\Http\Controllers\Grn\GoodsReceivedNoteController::class, 'show'])
        ->middleware('permission:grns.view')
        ->name('grns.show');
    Route::get('/grns/{grn}/edit', [\App\Http\Controllers\Grn\GoodsReceivedNoteController::class, 'edit'])
        ->middleware('permission:grns.update')
        ->name('grns.edit');
    Route::put('/grns/{grn}', [\App\Http\Controllers\Grn\GoodsReceivedNoteController::class, 'update'])
        ->middleware('permission:grns.update')
        ->name('grns.update');
    Route::delete('/grns/{grn}', [\App\Http\Controllers\Grn\GoodsReceivedNoteController::class, 'destroy'])
        ->middleware('permission:grns.delete')
        ->name('grns.destroy');

    Route::get('/stock', [\App\Http\Controllers\Stock\StockController::class, 'index'])
        ->middleware('permission:stock.view')
        ->name('stock.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/registration.php';
require __DIR__ . '/auth.php';
