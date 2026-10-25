<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PrediksiController;
use App\Http\Controllers\HistorisController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\ModelController;
use App\Http\Controllers\EvaluasiController;
use App\Http\Controllers\WawasanController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/prediksi', [PrediksiController::class, 'index'])->name('prediksi');
Route::get('/historis', [DataController::class, 'index'])->name('historis');
Route::get('/evaluasi', [EvaluasiController::class, 'index'])->name('evaluasi');
Route::get('/wawasan', [WawasanController::class, 'index'])->name('wawasan');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'dashboardadmin'])->name('dashboardadmin');
    Route::get('/cuaca', [DataController::class, 'cuaca'])->name('cuaca');
    Route::get('/harga', [DataController::class, 'harga'])->name('harga');
    Route::post('/harga/scrape', [DataController::class, 'scrapeHarga'])->name('harga.scrape');
    Route::post('/cuaca/scrape', [DataController::class, 'scrapeCuaca'])->name('cuaca.scrape');
    Route::get('/model', [ModelController::class, 'model'])->name('model');
    Route::get('/model', [ModelController::class, 'model'])->name('model');
    Route::post('/model/train', [ModelController::class, 'train'])->name('model.train');
    Route::get('/model/train/status', [ModelController::class, 'trainingStatus'])->name('model.train.status');

    Route::get('/pengguna', [UserController::class, 'index'])->name('pengguna');
    Route::post('/pengguna', [UserController::class, 'store'])->name('pengguna.store');
    Route::get('/pengguna/{id}/edit', [UserController::class, 'edit'])->name('pengguna.edit');
    Route::put('/pengguna/{id}', [UserController::class, 'update'])->name('pengguna.update');
    Route::delete('/pengguna/{id}', [UserController::class, 'delete'])->name('pengguna.delete');
});