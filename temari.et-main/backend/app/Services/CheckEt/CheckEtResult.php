<?php

namespace App\Services\CheckEt;

/**
 * Normalized verification outcome. `available=false` means we could not reach
 * a conclusive answer (provider down, quota exhausted, missing key) — the
 * caller parks the claim for staff review rather than failing the parent.
 */
final class CheckEtResult
{
    /**
     * @param  array<string, mixed>  $raw  Full provider response snapshot.
     */
    public function __construct(
        public readonly bool $available,
        public readonly bool $success,
        public readonly bool $exists,
        public readonly bool $duplicate,
        public readonly ?string $message,
        public readonly array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(array $response): self
    {
        return new self(
            available: true,
            success: (bool) ($response['success'] ?? false),
            exists: (bool) ($response['exists'] ?? false),
            duplicate: (bool) ($response['duplicate'] ?? false),
            message: $response['message'] ?? null,
            raw: $response,
        );
    }

    public static function unavailable(string $message): self
    {
        return new self(
            available: false,
            success: false,
            exists: false,
            duplicate: false,
            message: $message,
            raw: ['unavailable' => true, 'message' => $message],
        );
    }

    /** The parsed receipt block (amount, dates, parties) when present. */
    public function receipt(): array
    {
        $receipt = $this->raw['data']['receipt'] ?? null;

        return is_array($receipt) ? $receipt : [];
    }

    public function amount(): ?float
    {
        $amount = $this->receipt()['amount'] ?? null;

        return is_numeric($amount) ? (float) $amount : null;
    }

    public function receiverAccount(): ?string
    {
        $account = $this->receipt()['receiver_account'] ?? null;

        return is_string($account) && $account !== '' ? $account : null;
    }

    public function receiptStatus(): ?string
    {
        $status = $this->receipt()['status'] ?? null;

        return is_string($status) ? $status : null;
    }

    public function transactionNumber(): ?string
    {
        $number = $this->raw['data']['transaction_number'] ?? null;

        return is_string($number) && $number !== '' ? $number : null;
    }

    public function bankCode(): ?string
    {
        $bank = $this->raw['data']['bank'] ?? null;

        return is_string($bank) && $bank !== '' ? $bank : null;
    }
}
