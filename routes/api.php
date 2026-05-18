<?php

use App\Http\Controllers\Api\AdminShipmentController;
use App\Http\Controllers\Api\AuthOtpController;
use App\Http\Controllers\Api\ShipmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => ['status' => 'ok']);

    Route::post('/auth/otp/send', [AuthOtpController::class, 'send']);
    Route::post('/auth/otp/verify', [AuthOtpController::class, 'verify']);

    Route::get('/shipments', [ShipmentController::class, 'index']);
    Route::post('/shipments', [ShipmentController::class, 'store']);
    Route::get('/shipments/{packageNumber}', [ShipmentController::class, 'show']);

    Route::prefix('admin')->middleware('superadmin')->group(function () {
        Route::get('/shipments', [AdminShipmentController::class, 'index']);
        Route::patch('/shipments/{shipment}/status', [AdminShipmentController::class, 'updateStatus']);
        Route::post('/shipments/{shipment}/payment', [AdminShipmentController::class, 'recordPayment']);
        Route::post('/shipments/{shipment}/label-printed', [AdminShipmentController::class, 'markLabelPrinted']);
        Route::post('/shipments/{shipment}/postal-reference', [AdminShipmentController::class, 'recordPostalReference']);
    });
});
