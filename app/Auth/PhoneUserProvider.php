<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class PhoneUserProvider extends EloquentUserProvider
{
    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        if (empty($credentials) || !isset($credentials['phone'])) {
            return null;
        }

        $query = $this->newModelQuery();

        foreach ($credentials as $key => $value) {
            if ($key === 'phone') {
                $query->where($key, $value);
            } elseif (!str_contains($key, 'password')) {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        $plain = $credentials['password'];

        return $this->hasher->check($plain, $user->getAuthPassword());
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        // Try to find by phone first, then by ID as fallback
        $user = $this->newModelQuery()
            ->where('phone', $identifier)
            ->first();

        if (!$user && is_numeric($identifier)) {
            $user = $this->newModelQuery()->find($identifier);
        }

        return $user;
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        // Try phone first, then ID
        $model = $this->createModel();

        $retrieved = $model->newQuery()
            ->where('phone', $identifier)
            ->where($model->getRememberTokenName(), $token)
            ->first();

        if (!$retrieved && is_numeric($identifier)) {
            $retrieved = $model->newQuery()
                ->where($model->getKeyName(), $identifier)
                ->where($model->getRememberTokenName(), $token)
                ->first();
        }

        return $retrieved;
    }
}
 