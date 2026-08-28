<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageCategory extends Model
{
    protected $fillable = ['key', 'label_en', 'label_am', 'sms_allowed', 'is_active', 'sort_order'];

    protected $casts = [
        'sms_allowed' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(BulkMessage::class, 'category_id');
    }
}
