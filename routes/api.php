<?php

use App\Http\Controllers\Api\AdminCountryGroupController;
use App\Http\Controllers\Api\AdminGoodsTypeController;
use App\Http\Controllers\Api\AdminShipmentController;
use App\Http\Controllers\Api\AdminShippingDropLocationController;
use App\Http\Controllers\Api\AuthOtpController;
use App\Http\Controllers\Api\PublicGoodsTypeController;
use App\Http\Controllers\Api\PublicShippingDropLocationController;
use App\Http\Controllers\Api\PublicShippingQuoteController;
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

    // Public read-only list of goods types — drives the "Goods type" select
    // on the SendPackage form. Managed by superadmin (see below).
    Route::get('/goods-types', [PublicGoodsTypeController::class, 'index']);

    // Public shipping quote — internal pricing only (no carrier round-trip).
    // Returns the customer-facing amount for a (country, weight) pair using
    // the country-group tier tables managed by superadmin.
    Route::post('/shipping-quote', [PublicShippingQuoteController::class, 'quote']);

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

        Route::get('/goods-types', [AdminGoodsTypeController::class, 'index']);
        Route::post('/goods-types', [AdminGoodsTypeController::class, 'store']);
        Route::put('/goods-types/{goodsType}', [AdminGoodsTypeController::class, 'update']);
        Route::delete('/goods-types/{goodsType}', [AdminGoodsTypeController::class, 'destroy']);

        // Country groups + per-tier pricing — the new internal pricing model
        // replacing the old IL Post live-quote call. Each group bundles
        // destination countries; each tier sets customer_price + shipper_price
        // per weight bracket.
        Route::get('/country-groups', [AdminCountryGroupController::class, 'index']);
        Route::post('/country-groups', [AdminCountryGroupController::class, 'store']);
        Route::put('/country-groups/{countryGroup}', [AdminCountryGroupController::class, 'update']);
        Route::delete('/country-groups/{countryGroup}', [AdminCountryGroupController::class, 'destroy']);

        Route::post('/country-groups/{countryGroup}/countries', [AdminCountryGroupController::class, 'addCountry']);
        Route::delete('/country-groups/{countryGroup}/countries/{country}', [AdminCountryGroupController::class, 'removeCountry']);

        Route::post('/country-groups/{countryGroup}/tiers', [AdminCountryGroupController::class, 'addTier']);
        Route::put('/country-groups/{countryGroup}/tiers/{tier}', [AdminCountryGroupController::class, 'updateTier']);
        Route::delete('/country-groups/{countryGroup}/tiers/{tier}', [AdminCountryGroupController::class, 'destroyTier']);
    });
});
