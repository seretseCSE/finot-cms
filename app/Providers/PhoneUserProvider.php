<?php

namespace App\Providers;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;

class PhoneUserProvider extends EloquentUserProvider
{
    /**
     * Retrieve a user by the unique identifier.
     */
    public function retrieveById($identifier)
    {
        return $this->createModel()->newQuery()
            ->where(function ($query) use ($identifier) {
                $query->where('email', $identifier)
                      ->orWhere('phone', $identifier);
            })
            ->first();
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     */
    public function retrieveByToken($identifier, $token)
    {
        $model = $this->createModel();

        $retrievedModel = $model->newQuery()
            ->where($model->getAuthIdentifierName(), $identifier)
            ->where($model->getRememberTokenName(), $token)
            ->first();

        if (! $retrievedModel) {
            return null;
        }

        return $retrievedModel;
    }

    /**
     * Retrieve a user by the given credentials.
     */
    public function retrieveByCredentials(array $credentials)
    {
        if (empty($credentials[$this->createModel()->getEmailName()]) &&
            empty($credentials['phone'])) {
            return;
        }

        $query = $this->createModel()->newQuery();

        // Check if the identifier is an email or phone
        $identifier = $credentials[$this->createModel()->getEmailName()] ?? $credentials['phone'] ?? null;
        
        if ($identifier) {
            $query->where(function ($q) use ($identifier) {
                $q->where('email', $identifier)
                  ->orWhere('phone', $identifier);
            });
        }

        return $query->first();
    }
}
