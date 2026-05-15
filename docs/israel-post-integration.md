# Israel Post Integration

This API is intentionally separate from `b2c-api`, but the Israel Post shipment logic must be shared instead of copied.

## Current bridge

The first API slice stores package registration data locally and exposes admin endpoints for:

- recording branch payment and invoice number
- marking the shipping label as printed
- saving the postal reference number returned by the post office flow

Those endpoints let the UI run end-to-end while the real post-office gateway is extracted.

## Shared package target

Move the reusable postal/shipment logic from `../b2c-api` into a Composer package, then require it from both projects.

Suggested package:

```json
{
  "name": "shipperdev/israel-post-shipment",
  "autoload": {
    "psr-4": {
      "ShipperDev\\IsraelPostShipment\\": "src/"
    }
  }
}
```

Candidate source areas in `b2c-api`:

- `app/Services/IsraeliPostalCodeService.php`
- `modules/Shipments/Http/Requests/CustomerApi/CreateShipmentRateRequest.php`
- `modules/Shipments/Services/ShipmentRatesService.php`
- `modules/Rates/Entities/IsraelPostIntlCountry.php`
- `modules/Rates/Entities/IsraelPostCategoryPricing.php`
- `modules/Rates/Http/Controllers/AdministratorApi/v1/IsraelPostController.php`

The package should expose a small interface to this project, for example:

```php
interface IsraelPostShipmentGateway
{
    public function quote(IsraelPostQuoteData $data): IsraelPostQuoteResult;

    public function createShipment(IsraelPostShipmentData $data): IsraelPostShipmentResult;
}
```

Keep Laravel-specific controllers, guards, and request classes outside the package. The package should contain data objects, service classes, pricing/category lookup, postal-code lookup, and gateway/client logic.

## Next implementation step

After the package exists, replace the manual postal reference admin action in:

- `app/Http/Controllers/Api/AdminShipmentController.php`

with a call to `IsraelPostShipmentGateway::createShipment(...)`, then store the returned postal reference on the local `shipments` row.
