<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dev/ednerve-api-migrate', function () {

    abort_unless(
        request('key') === 'EdNerve@123',
        403
    );

    Artisan::call('migrate', ['--force' => true]);
    $migration = Artisan::output();

    Artisan::call('db:seed', ['--force' => true]);
    $seeding = Artisan::output();

    return response()->json([
        'message' => 'EdNerve migration and seeding completed',
        'migration' => $migration,
        'seeding' => $seeding,
    ]);
});

