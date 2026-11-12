<?php

use App\Http\Controllers\Finance\FinancialOverviewController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Notification\NotificationInboxController;
use App\Http\Controllers\Notification\NotificationLiveController;
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

    Route::get('/finance/overview', [FinancialOverviewController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('finance.overview');

    Route::get('/finance/overview/batches/{batch}', [FinancialOverviewController::class, 'showBatch'])
        ->middleware('permission:dashboard.view')
        ->name('finance.batches.show');

    Route::prefix('notifications')
        ->name('notifications.')
        ->group(function () {
            Route::get('/live', NotificationLiveController::class)
                ->middleware('permission:notifications.view')
                ->name('live');

            Route::prefix('inbox')
                ->name('inbox.')
                ->middleware('permission:notifications.view')
                ->group(function () {
                    Route::get('/', [NotificationInboxController::class, 'index'])
                        ->name('index');

                    Route::get('/{notification}', [NotificationInboxController::class, 'show'])
                        ->where('notification', '[A-Za-z0-9]+')
                        ->name('show');

                    Route::post('/{notification}/read', [NotificationInboxController::class, 'markRead'])
                        ->where('notification', '[A-Za-z0-9]+')
                        ->name('read');
                });

            Route::get('/', [NotificationController::class, 'index'])
                ->middleware('permission:notifications.view')
                ->name('index');

            Route::get('/create', [NotificationController::class, 'create'])
                ->middleware('permission:notifications.create')
                ->name('create');

            Route::post('/', [NotificationController::class, 'store'])
                ->middleware('permission:notifications.create')
                ->name('store');

            Route::get('/{notification}', [NotificationController::class, 'show'])
                ->middleware('permission:notifications.view')
                ->name('show');

            Route::get('/{notification}/edit', [NotificationController::class, 'edit'])
                ->middleware('permission:notifications.create')
                ->name('edit');

            Route::put('/{notification}', [NotificationController::class, 'update'])
                ->middleware('permission:notifications.create')
                ->name('update');
        });

    require __DIR__.'/orders.php';

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/registration.php';
require __DIR__ . '/auth.php';
