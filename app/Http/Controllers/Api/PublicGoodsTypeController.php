<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GoodsTypeResource;
use App\Models\GoodsType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only public list of goods types used by the customer "Goods type"
 * select on the SendPackage form. Only active rows are exposed. Admin CRUD
 * lives behind the superadmin middleware in AdminGoodsTypeController.
 */
class PublicGoodsTypeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $types = GoodsType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return GoodsTypeResource::collection($types);
    }
}
