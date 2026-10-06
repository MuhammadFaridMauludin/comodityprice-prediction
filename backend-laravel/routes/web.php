<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PrediksiController;
use App\Http\Controllers\HistorisController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\ModelController;
use App\Http\Controllers\EvaluasiController;
use App\Http\Controllers\WawasanController;

use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/prediksi', [PrediksiController::class, 'index'])->name('prediksi');
Route::get('/historis', [HistorisController::class, 'index'])->name('historis');
Route::get('/evaluasi', [EvaluasiController::class, 'index'])->name('evaluasi');
Route::get('/wawasan', [WawasanController::class, 'index'])->name('wawasan');

Route::get('/dashboard', [DashboardController::class, 'dashboardadmin'])->name('dashboardadmin');
Route::get('/cuaca', [DataController::class, 'cuaca'])->name('cuaca');
Route::get('/peng', [DataController::class, 'harga'])->name('harga');
Route::get('/pengguna', [DataController::class, 'pengguna'])->name('pengguna');
Route::post('/pengguna', [DataController::class, 'penggunaStore'])->name('pengguna.store');
Route::put('/pengguna/{id}', [DataController::class, 'penggunaUpdate'])->whereNumber('id')->name('pengguna.update');
Route::delete('/pengguna/{id}', [DataController::class, 'penggunaDestroy'])->whereNumber('id')->name('pengguna.destroy');
Route::get('/model', [ModelController::class, 'model'])->name('model');
// foreach (['notif' => 'Notif', 'profil' => 'Profil'] as $uri => $title) {
//     Route::view("/$uri", 'pages.placeholder', ['title' => $title])->name($uri);
// }
