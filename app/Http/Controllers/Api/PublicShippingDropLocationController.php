<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShippingDropLocationResource;
use App\Models\ShippingDropLocation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only public endpoint used by the customer-facing "View branches on map"
 * popup. Only active locations are exposed; admin CRUD lives in
 * AdminShippingDropLocationController behind the superadmin middleware.
 */
class PublicShippingDropLocationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $locations = ShippingDropLocation::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ShippingDropLocationResource::collection($locations);
    }
}
