<?php

use App\Http\Controllers\Api\AdminShipmentController;
use App\Http\Controllers\Api\AdminShippingDropLocationController;
use App\Http\Controllers\Api\AuthOtpController;
use App\Http\Controllers\Api\PublicShippingDropLocationController;
use App\Http\Controllers\Api\ShipmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => ['status' => 'ok']);

    Route::post('/auth/otp/send', [AuthOtpController::class, 'send']);
    Route::post('/auth/otp/verify', [AuthOtpController::class, 'verify']);

    Route::get('/shipments', [ShipmentController::class, 'index']);
    Route::post('/shipments', [ShipmentController::class, 'store']);
    Route::get('/shipments/{packageNumber}', [ShipmentController::class, 'show']);

    // Public read-only list of active drop-off branches — used by the
    // "View branches on map" popup at the end of the SendPackage flow.
    Route::get('/shipping-locations', [PublicShippingDropLocationController::class, 'index']);

    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/ems/status', [AdminShipmentController::class, 'emsStatus']);
        Route::get('/shipments', [AdminShipmentController::class, 'index']);
        Route::patch('/shipments/{shipment}/status', [AdminShipmentController::class, 'updateStatus']);
        Route::post('/shipments/{shipment}/shipping-quote', [AdminShipmentController::class, 'quoteShipping']);
        Route::post('/shipments/{shipment}/payment', [AdminShipmentController::class, 'recordPayment']);
        Route::post('/shipments/{shipment}/label-printed', [AdminShipmentController::class, 'markLabelPrinted']);
        Route::post('/shipments/{shipment}/postal-reference', [AdminShipmentController::class, 'recordPostalReference']);
    });

    Route::prefix('admin')->middleware('superadmin')->group(function () {
        Route::get('/drop-locations', [AdminShippingDropLocationController::class, 'index']);
        Route::post('/drop-locations', [AdminShippingDropLocationController::class, 'store']);
        Route::put('/drop-locations/{dropLocation}', [AdminShippingDropLocationController::class, 'update']);
        Route::delete('/drop-locations/{dropLocation}', [AdminShippingDropLocationController::class, 'destroy']);
    });
});
