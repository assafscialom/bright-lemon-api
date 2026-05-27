<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShippingDropLocationResource;
use App\Models\ShippingDropLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AdminShippingDropLocationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'active' => ['nullable', Rule::in(['all', 'true', 'false'])],
        ]);

        $query = ShippingDropLocation::query()
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('name');

        if (($data['active'] ?? 'all') !== 'all') {
            $query->where('is_active', $data['active'] === 'true');
        }

        if (! empty($data['search'])) {
            $term = '%'.$data['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('city', 'like', $term)
                    ->orWhere('address_line_1', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        return ShippingDropLocationResource::collection($query->get());
    }

    public function store(Request $request): ShippingDropLocationResource
    {
        $location = ShippingDropLocation::create($this->validatedData($request));

        return new ShippingDropLocationResource($location);
    }

    public function update(Request $request, ShippingDropLocation $dropLocation): ShippingDropLocationResource
    {
        $dropLocation->update($this->validatedData($request, $dropLocation));

        return new ShippingDropLocationResource($dropLocation->refresh());
    }

    public function destroy(ShippingDropLocation $dropLocation): ShippingDropLocationResource
    {
        $dropLocation->update(['is_active' => false]);

        return new ShippingDropLocationResource($dropLocation->refresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?ShippingDropLocation $location = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('shipping_drop_locations', 'code')->ignore($location),
            ],
            // "מספר שיפר" — Shipper customer/account number tied to this branch.
            // Optional: not every branch has one yet.
            'shipper_number' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'opening_hours' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
        ]);
    }
}
