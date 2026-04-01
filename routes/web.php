<?php

use Illuminate\Support\Facades\Route;
use App\Services\Calculator;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/hitung', function (Calculator $calc) {
    $hasil = $calc->add(10, 5);

    return response()->json([
        'operasi' => '10 + 5',
        'hasil' => $hasil,
    ]);
});

Route::get('/tambah', function (Calculator $calc) {
    $hasil = $calc->add(10, 15);

    return response()->json([
        'operasi' => '10 + 15',
        'hasil' => $hasil,
    ]);
});
