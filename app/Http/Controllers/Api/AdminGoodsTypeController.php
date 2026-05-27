<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GoodsTypeResource;
use App\Models\GoodsType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AdminGoodsTypeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'active' => ['nullable', Rule::in(['all', 'true', 'false'])],
        ]);

        $query = GoodsType::query()
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('name');

        if (($data['active'] ?? 'all') !== 'all') {
            $query->where('is_active', $data['active'] === 'true');
        }

        if (! empty($data['search'])) {
            $query->where('name', 'like', '%'.$data['search'].'%');
        }

        return GoodsTypeResource::collection($query->get());
    }

    public function store(Request $request): GoodsTypeResource
    {
        $type = GoodsType::create($this->validatedData($request));

        return new GoodsTypeResource($type);
    }

    public function update(Request $request, GoodsType $goodsType): GoodsTypeResource
    {
        $goodsType->update($this->validatedData($request));

        return new GoodsTypeResource($goodsType->refresh());
    }

    public function destroy(GoodsType $goodsType): GoodsTypeResource
    {
        // Soft-disable instead of hard delete so historical shipments that
        // pointed at this label keep their text intact.
        $goodsType->update(['is_active' => false]);

        return new GoodsTypeResource($goodsType->refresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
        ]);
    }
}
