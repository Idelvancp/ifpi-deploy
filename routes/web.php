<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\DockerController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Rotas do Docker (protegidas por autenticação)
Route::middleware(['auth'])->group(function () {
    // API Docker
    Route::prefix('api/docker')->group(function () {
        Route::get('/containers', [DockerController::class, 'index'])->name('docker.containers');
        Route::post('/container/create', [DockerController::class, 'create'])->name('docker.create');
        Route::get('/containers/{containerId}', [DockerController::class, 'show'])->name('docker.show');
        Route::post('/container/{containerId}/start', [DockerController::class, 'start'])->name('docker.start');
        Route::post('/container/{containerId}/stop', [DockerController::class, 'stop'])->name('docker.stop');
        Route::delete('/container/{containerId}', [DockerController::class, 'destroy'])->name('docker.destroy');
        Route::get('/containers/{containerId}/logs', [DockerController::class, 'logs'])->name('docker.logs');
        Route::get('/status', [DockerController::class, 'status'])->name('docker.status');
    });

    // Interface Web do Docker (página)
    Route::get('/docker', function () {
        return view('docker.index');
    })->name('docker.index');
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
