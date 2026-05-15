<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        ];
    }
}
