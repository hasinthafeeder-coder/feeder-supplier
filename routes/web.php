<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

use Feeder\Core\Helpers\Test;

// ----------------- Test Route for dropshipping-core package ----------------------------------------------
Route::get('package-test', function () {
    return Test::hello();
});
// ---------------------------------------------------------------------------------------------------------

// ----------------- Test Route for template ---------------------------------------------------------------
Route::get('/test', function () {
    return view('test_index');
});
// ---------------------------------------------------------------------------------------------------------


Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
