<?php

namespace App\Services\CheckEt;

use Illuminate\Http\UploadedFile;

interface CheckEtClient
{
    /** Bank/wallet codes check.et can verify, in THEIR code vocabulary. */
    public const array SUPPORTED_BANKS = [
        'cbe', 'telebirr', 'dashen', 'awash', 'boa',
        'zemen', 'cbebirr', 'mpesa', 'sinqee', 'amhara',
    ];

    /** Our banks-catalog codes → check.et codes (identity unless listed). */
    public const array LOCAL_CODE_MAP = [
        'siinqee' => 'sinqee',
    ];

    /**
     * Verify a payment against bank records. Exactly one input group:
     * bank+transaction_number, receipt_url, or receipt_file.
     *
     * @param  array{
     *     bank?: ?string,
     *     transaction_number?: ?string,
     *     account_number?: ?string,
     *     receipt_url?: ?string,
     *     receipt_file?: ?UploadedFile,
     * }  $payload
     * @return CheckEtResult never throws for business outcomes — transport
     *                       failures surface as an unavailable result.
     */
    public function verify(array $payload): CheckEtResult;
}
