<?php

namespace App\Services\Payments;

/**
 * A gateway's answer about one transaction: paid, still pending, or failed —
 * plus the raw payload we archive for audit.
 */
final readonly class GatewayVerdict
{
    private function __construct(
        public string $outcome, // paid | pending | failed
        public ?string $gatewayRef,
        public ?string $reason,
        /** @var array<string, mixed> */
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function paid(?string $gatewayRef, array $raw = []): self
    {
        return new self('paid', $gatewayRef, null, $raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function pending(array $raw = []): self
    {
        return new self('pending', null, null, $raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function failed(?string $reason, array $raw = []): self
    {
        return new self('failed', null, $reason, $raw);
    }
}
