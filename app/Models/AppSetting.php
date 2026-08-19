<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One admin-editable global value. Read through App\Support\Settings, which
 * caches and casts — this model is the storage, not the interface.
 */
class AppSetting extends Model
{
    protected $guarded = [];
}
