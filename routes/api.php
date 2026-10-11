<?php

use App\Exceptions\OrderCannotProcessException;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\ProductController;
use App\Http\Middleware\CheckApiTokenAbility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', CheckApiTokenAbility::class])->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class);
});

if (app()->environment('local', 'testing')) {
    Route::prefix('section3')->middleware(['auth:sanctum', CheckApiTokenAbility::class])->group(function () {
        Route::get('/business-error', fn () => throw new OrderCannotProcessException(42));
        Route::get('/server-error', fn () => throw new RuntimeException('password=never-expose-this-secret'));
        Route::post('/validate', function (Request $request) {
            $data = $request->validate(['name' => 'required|string|max:100']);

            return response()->json(['data' => $data]);
        });
        Route::get('/log', function () {
            Log::info('Section 3 logging demo completed.', ['demo' => 'section3']);

            return response()->json(['message' => 'Đã ghi log.', 'request_id' => request()->attributes->get('request_id')]);
        });
    });
}
