<?php

namespace App\Extensions;

use Illuminate\Auth\CreatesUserProviders;

class PhoneAuthExtension
{
    use CreatesUserProviders;

    /**
     * Register the phone driver.
     */
    public function createPhoneDriver()
    {
        return $this->createUserProvider('phone');
    }
}
