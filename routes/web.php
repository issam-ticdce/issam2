<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StartupController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'redirect']);

// Aperçus des versions en attente (connexion requise)
Route::get('/apercu/startups/{startup}', [StartupController::class, 'preview'])->name('preview.startup');
Route::get('/apercu/products/{product}', [ProductController::class, 'preview'])->name('preview.product');

Route::prefix('{locale}')
    ->where(['locale' => implode('|', config('ticdce.locales'))])
    ->middleware(SetLocale::class)
    ->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('home');
        Route::get('/startups', [StartupController::class, 'index'])->name('startups.index');
        Route::get('/startups/{startup}', [StartupController::class, 'show'])->name('startups.show');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::post('/startups/{startup}/inquiries', [InquiryController::class, 'store'])
            ->middleware('throttle:5,10')
            ->name('inquiries.store');
    });
