<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ApplyController;

Route::apiResource('posts', PostController::class);
Route::apiResource('applies', ApplyController::class);

Route::get('/share/{share}', [PostController::class, 'showShare']);
Route::get('/dashboard/{dashboard}', [PostController::class, 'showDashboard'])->middleware('check.access.token');
Route::delete('/dashboard/{dashboard}', [PostController::class, 'destroyDashboard'])->middleware('check.access.token');
Route::post('/interview/{apply}/{lang?}', [ApplyController::class, 'interview']);

