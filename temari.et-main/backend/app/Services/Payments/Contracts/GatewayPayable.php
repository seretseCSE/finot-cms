<?php

namespace App\Services\Payments\Contracts;

use App\Models\GatewayTransaction;

/**
 * A thing that can be paid for through a gateway (a tutoring cycle, a boost,
 * a subscription…). Fulfilment runs inside the manager's settle transaction,
 * exactly once per transaction — implementations must be idempotent against
 * their own state (e.g. a cycle already funded ignores a second paid tx).
 */
interface GatewayPayable
{
    /** Human line for checkout + receipts ("Tutoring — Meskerem 2019"). */
    public function gatewayDescription(): string;

    /** React to the money arriving (activate, fund, extend…). */
    public function gatewayPaid(GatewayTransaction $transaction): void;
}
