<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Model;

trait HasEthiopianDates
{
    /**
     * Boot the trait.
     */
    protected static function bootHasEthiopianDates(): void
    {
        static::created(function (Model $model) {
            if (in_array('App\Models\Traits\HasEthiopianDates', class_uses($model))) {
                $model->setEthiopianTimestamps();
            }
        });
    }

    /**
     * Set Ethiopian timestamps on the model.
     */
    protected function setEthiopianTimestamps(): void
    {
        $this->setCreatedAtFormat('M d, Y H:i:s');
        $this->setUpdatedAtFormat('M d, Y H:i:s');
    }

    /**
     * Get Ethiopian created at attribute.
     */
    public function getEthiopianCreatedAtAttribute(): string
    {
        return $this->created_at ? $this->created_at->format('M d, Y H:i:s') : '';
    }

    /**
     * Get Ethiopian updated at attribute.
     */
    public function getEthiopianUpdatedAtAttribute(): string
    {
        return $this->updated_at ? $this->updated_at->format('M d, Y H:i:s') : '';
    }
}
