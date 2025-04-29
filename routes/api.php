<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhoisController;
use App\Http\Middleware\RefererCheckMiddleware;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Referer请求头检查是否合法
Route::middleware(RefererCheckMiddleware::class)->group(function () {
    // 获取Whois信息
    Route::get('/whois', [WhoisController::class, 'query']);
    // 获取WHOIS服务器列表
    Route::get('/whois/servers', [WhoisController::class, 'servers']);
});
