<?php
use Illuminate\Support\Facades\Route;
Route::get('/', fn () => response()->json(['app' => 'Mini Booking', 'frontend' => 'http://localhost:8080', 'slots' => '/api/slots']));
