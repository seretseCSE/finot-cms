<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordHistory extends Model
{
    protected $fillable = [
        'user_id',
        'password_hash',
    ];

    /**
     * Get the user that owns this password history.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Keep only last 3 password records for each user
     */
    protected static function boot(): void
    {
        parent::boot();

        static::created(function ($passwordHistory) {
            // Get all password histories for this user, ordered by creation date
            $histories = static::where('user_id', $passwordHistory->user_id)
                ->orderBy('created_at', 'desc')
                ->get();

            // If we have more than 3, delete the oldest ones
            if ($histories->count() > 3) {
                $toDelete = $histories->skip(3)->pluck('id');
                static::whereIn('id', $toDelete)->delete();
            }
        });
    }
    
}
