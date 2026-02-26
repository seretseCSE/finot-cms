<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Model;

trait GeneratesAutoId
{
    /**
     * Boot the trait.
     */
    protected static function bootGeneratesAutoId(): void
    {
        static::creating(function (Model $model) {
            if (in_array('App\Models\Traits\GeneratesAutoId', class_uses($model))) {
                $model->generateAutoId();
            }
        });
    }

    /**
     * Generate auto-incrementing ID in M-000001 format.
     */
    protected function generateAutoId(): void
    {
        // Get the highest existing ID
        $maxId = static::max('id') ?? 0;
        
        // Generate new ID in M-000001 format
        $newId = str_pad($maxId + 1, 6, '0', STR_PAD_LEFT);
        
        $this->setAttribute('id', $newId);
    }
}
