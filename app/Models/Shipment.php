<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    public const STATUS_REGISTERED = 'Registered';
    public const STATUS_PENDING_PAYMENT = 'Pending Payment';
    public const STATUS_PAID = 'Paid';
    public const STATUS_LABEL_PRINTED = 'Label Printed';
    public const STATUS_IN_TRANSIT = 'In Transit';
    public const STATUS_OUT_FOR_DELIVERY = 'Out for Delivery';
    public const STATUS_DELIVERED = 'Delivered';

    public const STATUSES = [
        self::STATUS_REGISTERED,
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PAID,
        self::STATUS_LABEL_PRINTED,
        self::STATUS_IN_TRANSIT,
        self::STATUS_OUT_FOR_DELIVERY,
        self::STATUS_DELIVERED,
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sender_passport_expires_at' => 'date',
            'declared_value' => 'decimal:2',
            'shipping_price' => 'decimal:2',
            'weight_kg' => 'decimal:2',
            'paid_at' => 'datetime',
            'label_printed_at' => 'datetime',
            'ems_created_at' => 'datetime',
            'shipping_quoted_at' => 'datetime',
        ];
    }

    /**
     * The drop-off branch that accepted this shipment. Assigned at payment
     * time; null until then. Drives the revenue report's per-branch filter.
     */
    public function dropLocation(): BelongsTo
    {
        return $this->belongsTo(ShippingDropLocation::class, 'drop_location_id');
    }
}
