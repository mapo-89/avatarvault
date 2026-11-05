<?php

use App\Http\Controllers\ApiAvatarUploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', fn (Request $request) => $request->user())->middleware('auth:api');

Route::post('/avatars/upload', ApiAvatarUploadController::class)->middleware('avatar-manager.api_key');
