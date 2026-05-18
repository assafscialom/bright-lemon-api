<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\PhoneService;
use App\Services\ShipmentPricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ShipmentController extends Controller
{
    public function index(Request $request, PhoneService $phones): AnonymousResourceCollection
    {
        $data = $request->validate([
            'country_code' => ['nullable', 'string', 'max:8'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $query = Shipment::query()->latest();

        if (! empty($data['mobile'])) {
            $phone = $phones->normalize($data['country_code'] ?? '', $data['mobile']);
            $query->where(function ($q) use ($phone) {
                $q->where('sender_phone_normalized', $phone)
                    ->orWhere('recipient_phone_normalized', $phone);
            });
        }

        if (! empty($data['search'])) {
            $this->applySearch($query, $data['search']);
        }

        return ShipmentResource::collection($query->get());
    }

    public function store(
        Request $request,
        ShipmentPricingService $pricing,
        PhoneService $phones
    ): ShipmentResource {
        $data = $request->validate([
            'sender.first_name' => ['required', 'string', 'max:100'],
            'sender.middle_name' => ['nullable', 'string', 'max:100'],
            'sender.last_name' => ['required', 'string', 'max:100'],
            'sender.country_code' => ['required', 'string', 'max:8'],
            'sender.mobile' => ['required', 'string', 'max:30'],

            'passport.number' => ['required', 'string', 'max:50'],
            'passport.expires_at' => ['required', 'date', 'after:today'],
            'passport.file' => ['nullable', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'],
            'signature_data_url' => ['nullable', 'string'],

            'customs.package_type' => ['required', 'string', 'max:80'],
            'customs.destination' => ['required', 'string', 'max:120'],
            'customs.weight' => ['required', 'string', 'max:40'],
            'customs.declared_value' => ['required', 'numeric', 'min:0', 'max:999999.99'],

            'recipient.first_name' => ['required', 'string', 'max:100'],
            'recipient.middle_name' => ['nullable', 'string', 'max:100'],
            'recipient.last_name' => ['required', 'string', 'max:100'],
            'recipient.state' => ['nullable', 'string', 'max:120'],
            'recipient.city' => ['required', 'string', 'max:120'],
            'recipient.street' => ['required', 'string', 'max:160'],
            'recipient.number' => ['nullable', 'string', 'max:30'],
            'recipient.po_box' => ['nullable', 'string', 'max:50'],
            'recipient.postal_code' => ['nullable', 'string', 'max:30'],
            'recipient.country_code' => ['required', 'string', 'max:8'],
            'recipient.mobile' => ['required', 'string', 'max:30'],
        ]);

        $passportPath = $request->file('passport.file')?->store('passports', 'public');

        $shipment = DB::transaction(function () use ($data, $pricing, $phones, $passportPath) {
            $packageNumber = $this->generatePackageNumber();

            return Shipment::create([
                'package_number' => $packageNumber,
                'status' => Shipment::STATUS_REGISTERED,
                'branch_name' => config('brightlemon.default_branch'),

                'sender_first_name' => $data['sender']['first_name'],
                'sender_middle_name' => $data['sender']['middle_name'] ?? null,
                'sender_last_name' => $data['sender']['last_name'],
                'sender_country_code' => $data['sender']['country_code'],
                'sender_mobile' => $data['sender']['mobile'],
                'sender_phone_normalized' => $phones->normalize($data['sender']['country_code'], $data['sender']['mobile']),
                'sender_passport_number' => $data['passport']['number'],
                'sender_passport_expires_at' => $data['passport']['expires_at'],
                'passport_file_path' => $passportPath,
                'signature_data_url' => $data['signature_data_url'] ?? null,

                'package_type' => $data['customs']['package_type'],
                'destination_country' => $data['customs']['destination'],
                'weight_label' => $data['customs']['weight'],
                'weight_kg' => $pricing->weightKgForLabel($data['customs']['weight']),
                'declared_value' => $data['customs']['declared_value'],
                'shipping_price' => $pricing->priceForWeightLabel($data['customs']['weight']),

                'recipient_first_name' => $data['recipient']['first_name'],
                'recipient_middle_name' => $data['recipient']['middle_name'] ?? null,
                'recipient_last_name' => $data['recipient']['last_name'],
                'recipient_state' => $data['recipient']['state'] ?? null,
                'recipient_city' => $data['recipient']['city'],
                'recipient_street' => $data['recipient']['street'],
                'recipient_number' => $data['recipient']['number'] ?? null,
                'recipient_po_box' => $data['recipient']['po_box'] ?? null,
                'recipient_postal_code' => $data['recipient']['postal_code'] ?? null,
                'recipient_country_code' => $data['recipient']['country_code'],
                'recipient_mobile' => $data['recipient']['mobile'],
                'recipient_phone_normalized' => $phones->normalize($data['recipient']['country_code'], $data['recipient']['mobile']),
            ]);
        });

        return new ShipmentResource($shipment);
    }

    public function show(string $packageNumber): ShipmentResource
    {
        return new ShipmentResource(
            Shipment::query()->where('package_number', $packageNumber)->firstOrFail()
        );
    }

    private function generatePackageNumber(): string
    {
        do {
            $number = (string) random_int(10000000, 99999999);
        } while (Shipment::query()->where('package_number', $number)->exists());

        return $number;
    }

    private function applySearch($query, string $search): void
    {
        $term = '%'.$search.'%';

        $query->where(function ($q) use ($term) {
            $q->where('package_number', 'like', $term)
                ->orWhere('status', 'like', $term)
                ->orWhere('sender_first_name', 'like', $term)
                ->orWhere('sender_last_name', 'like', $term)
                ->orWhere('sender_mobile', 'like', $term)
                ->orWhere('recipient_first_name', 'like', $term)
                ->orWhere('recipient_last_name', 'like', $term)
                ->orWhere('recipient_city', 'like', $term)
                ->orWhere('destination_country', 'like', $term)
                ->orWhere('payment_ref', 'like', $term)
                ->orWhere('invoice_number', 'like', $term)
                ->orWhere('postal_ref', 'like', $term);
        });
    }
}
