<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryGroupPriceTierResource;
use App\Http\Resources\CountryGroupResource;
use App\Models\CountryGroup;
use App\Models\CountryGroupCountry;
use App\Models\CountryGroupPriceTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Superadmin-only CRUD for the country-group pricing system. Groups bundle
 * destination countries; each group has weight-tier prices (customer + shipper)
 * that drive the new shipment quote. Replaces the old IL Post live-quote call.
 */
class AdminCountryGroupController extends Controller
{
    // ---- Groups ----

    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'active' => ['nullable', Rule::in(['all', 'true', 'false'])],
        ]);

        $query = CountryGroup::query()
            ->with(['countries', 'priceTiers'])
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('name');

        if (($data['active'] ?? 'all') !== 'all') {
            $query->where('is_active', $data['active'] === 'true');
        }
        if (! empty($data['search'])) {
            $query->where('name', 'like', '%'.$data['search'].'%');
        }

        return CountryGroupResource::collection($query->get());
    }

    public function store(Request $request): CountryGroupResource
    {
        $group = CountryGroup::create($this->validatedGroup($request));
        $group->load(['countries', 'priceTiers']);

        return new CountryGroupResource($group);
    }

    public function update(Request $request, CountryGroup $countryGroup): CountryGroupResource
    {
        $countryGroup->update($this->validatedGroup($request));
        $countryGroup->load(['countries', 'priceTiers']);

        return new CountryGroupResource($countryGroup);
    }

    public function destroy(CountryGroup $countryGroup): JsonResponse
    {
        // Soft-disable instead of hard delete so historical shipments quoted
        // against this group keep their context.
        $countryGroup->update(['is_active' => false]);

        return response()->json(['data' => ['id' => $countryGroup->id, 'is_active' => false]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedGroup(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
        ]);
    }

    // ---- Countries in a group ----

    public function addCountry(Request $request, CountryGroup $countryGroup): JsonResponse
    {
        $data = $request->validate([
            'country_code' => ['required', 'string', 'size:2'],
            'country_name' => ['required', 'string', 'max:120'],
        ]);

        // A country can only live in one group at a time. If it's already in a
        // different group, move it (the unique index would 500 us otherwise).
        $code = strtoupper($data['country_code']);
        $existing = CountryGroupCountry::where('country_code', $code)->first();
        if ($existing) {
            $existing->update([
                'country_group_id' => $countryGroup->id,
                'country_name' => $data['country_name'],
            ]);
            $entry = $existing->refresh();
        } else {
            $entry = CountryGroupCountry::create([
                'country_group_id' => $countryGroup->id,
                'country_code' => $code,
                'country_name' => $data['country_name'],
            ]);
        }

        return response()->json(['data' => [
            'id' => $entry->id,
            'country_group_id' => $entry->country_group_id,
            'country_code' => $entry->country_code,
            'country_name' => $entry->country_name,
        ]]);
    }

    public function removeCountry(CountryGroup $countryGroup, CountryGroupCountry $country): JsonResponse
    {
        if ($country->country_group_id !== $countryGroup->id) {
            return response()->json(['message' => 'Country does not belong to this group.'], 404);
        }
        $country->delete();
        return response()->json(['data' => ['id' => $country->id, 'removed' => true]]);
    }

    // ---- Price tiers in a group ----

    public function addTier(Request $request, CountryGroup $countryGroup): CountryGroupPriceTierResource
    {
        $data = $this->validatedTier($request);
        $tier = $countryGroup->priceTiers()->create($data);

        return new CountryGroupPriceTierResource($tier);
    }

    public function updateTier(Request $request, CountryGroup $countryGroup, CountryGroupPriceTier $tier): CountryGroupPriceTierResource
    {
        if ($tier->country_group_id !== $countryGroup->id) {
            abort(404, 'Tier does not belong to this group.');
        }
        $tier->update($this->validatedTier($request));

        return new CountryGroupPriceTierResource($tier->refresh());
    }

    public function destroyTier(CountryGroup $countryGroup, CountryGroupPriceTier $tier): JsonResponse
    {
        if ($tier->country_group_id !== $countryGroup->id) {
            return response()->json(['message' => 'Tier does not belong to this group.'], 404);
        }
        $tier->delete();
        return response()->json(['data' => ['id' => $tier->id, 'removed' => true]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedTier(Request $request): array
    {
        return $request->validate([
            'max_weight_kg' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'customer_price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'shipper_price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);
    }
}
