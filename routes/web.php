<?php

use App\Http\Controllers\ItemLabelController;
use App\Http\Controllers\PublicItemController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

// Öffentliche Statusseite hinter dem QR-Code: ohne Login (R12–R14).
Route::get('i/{publicId}', [PublicItemController::class, 'show'])->name('public.items.show');
Route::post('i/{publicId}/found', [PublicItemController::class, 'report'])
    ->middleware('throttle:5,1')
    ->name('public.items.found');

// Hinweisseite für Konten, die noch auf Freischaltung durch einen Admin warten.
Route::middleware('auth')->get('pending', function () {
    return auth()->user()->isPending() ? view('pending') : redirect()->route('dashboard');
})->name('pending');

Route::middleware(['auth', 'verified', 'approved'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::livewire('items', 'pages::items.index')->name('items.index');
    Route::livewire('items/create', 'pages::items.form')->name('items.create');
    Route::livewire('items/{item}', 'pages::items.show')->name('items.show');
    Route::livewire('items/{item}/edit', 'pages::items.form')->name('items.edit');
    Route::get('items/{item}/label', ItemLabelController::class)->name('items.label');

    Route::livewire('locations', 'pages::locations.index')->name('locations.index');
    Route::livewire('categories', 'pages::categories.index')->name('categories.index');
    Route::livewire('loans', 'pages::loans.index')->name('loans.index');
    Route::livewire('found-reports', 'pages::found-reports.index')->name('found-reports.index');

    Route::livewire('users', 'pages::users.index')->name('users.index');
    Route::livewire('audit', 'pages::audit.index')->name('audit.index');
});

require __DIR__.'/settings.php';
