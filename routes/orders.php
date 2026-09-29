<?php

use App\Http\Controllers\Order\OrderFulfillmentController;
use App\Http\Controllers\Order\OrderListController;
use App\Http\Controllers\Order\OrderPackagingController;
use App\Http\Controllers\Order\OrderPrintController;
use App\Http\Controllers\Order\OrderUiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Supplier Portal Orders — Phase 2A HTTP/API + UI layer
|--------------------------------------------------------------------------
*/

Route::prefix('orders')->group(function () {
    /*
    | UI pages (Blade). JSON list APIs stay on distinct paths below.
    | Old /new/* UI paths redirect into the unified New Orders page.
    */
    Route::get('/new', [OrderUiController::class, 'newOrders'])
        ->middleware('permission:orders.view')
        ->name('orders.ui.new');

    Route::get('/new/fresh', function () {
        return redirect()->route('orders.ui.new', ['mode' => 'fresh']);
    })->middleware('permission:orders.view')->name('orders.ui.fresh');

    Route::get('/new/out-of-stock', function () {
        return redirect()->route('orders.ui.new', ['mode' => 'out-of-stock']);
    })->middleware('permission:orders.view')->name('orders.ui.out-of-stock');

    Route::get('/new/exchange', function () {
        return redirect()->route('orders.ui.new', ['mode' => 'exchange']);
    })->middleware('permission:orders.view')->name('orders.ui.exchange');

    Route::get('/print-orders', [OrderUiController::class, 'print'])
        ->middleware('permission:orders.view')
        ->name('orders.ui.print');

    Route::get('/packaging-orders', [OrderUiController::class, 'packaging'])
        ->middleware('permission:orders.view')
        ->name('orders.ui.packaging');

    /*
    | JSON list / action APIs (Step 3)
    */
    Route::get('/fresh', [OrderListController::class, 'fresh'])
        ->middleware('permission:orders.view')
        ->name('orders.fresh');

    Route::get('/out-of-stock', [OrderListController::class, 'outOfStock'])
        ->middleware('permission:orders.view')
        ->name('orders.out-of-stock');

    Route::get('/print', [OrderListController::class, 'print'])
        ->middleware('permission:orders.view')
        ->name('orders.print.index');

    Route::get('/packaging', [OrderListController::class, 'packaging'])
        ->middleware('permission:orders.view')
        ->name('orders.packaging.index');

    Route::post('/send-to-print', [OrderFulfillmentController::class, 'sendToPrint'])
        ->middleware('permission:orders.send-to-print')
        ->name('orders.send-to-print');

    Route::post('/print-data', [OrderPrintController::class, 'prepare'])
        ->middleware('permission:orders.print')
        ->name('orders.print-data');

    Route::post('/reprint-data', [OrderPrintController::class, 'reprint'])
        ->middleware('permission:orders.print')
        ->name('orders.reprint-data');

    Route::post('/send-to-packing', [OrderFulfillmentController::class, 'sendToPackaging'])
        ->middleware('permission:orders.send-to-packing')
        ->name('orders.send-to-packing');

    Route::post('/packaging/scan-order', [OrderPackagingController::class, 'scanOrder'])
        ->middleware('permission:orders.pack')
        ->name('orders.packaging.scan-order');

    Route::post('/packaging/scan-product', [OrderPackagingController::class, 'scanProduct'])
        ->middleware('permission:orders.pack')
        ->name('orders.packaging.scan-product');

    Route::post('/packaging/complete', [OrderPackagingController::class, 'complete'])
        ->middleware('permission:orders.pack')
        ->name('orders.packaging.complete');

    Route::post('/{order}/cancel', [OrderPackagingController::class, 'cancel'])
        ->middleware('permission:orders.cancel')
        ->name('orders.cancel');

    Route::get('/{order}', [OrderUiController::class, 'show'])
        ->middleware('permission:orders.view')
        ->name('orders.show');
});
