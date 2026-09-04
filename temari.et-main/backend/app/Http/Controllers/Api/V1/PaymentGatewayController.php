<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\GatewayPurpose;
use App\Http\Controllers\Controller;
use App\Models\GatewayTransaction;
use App\Models\PlatformSetting;
use App\Support\Marketplace;
use App\Support\PaymentGateways;
use App\Support\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Platform operator console for the payment gateways (`gateways.manage`):
 * the enable/purpose matrix, marketplace money knobs (commission, boost
 * pricing, release mode) and the cross-platform transaction register.
 * Credentials are env-only — this endpoint never sees or returns a secret.
 */
class PaymentGatewayController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $this->authorizePlatform($request);

        return response()->json(['data' => $this->payload()]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizePlatform($request);

        $purposes = array_map(fn (GatewayPurpose $p) => $p->value, GatewayPurpose::cases());

        $data = $request->validate([
            'gateways' => ['required', 'array'],
            'gateways.*.enabled' => ['required', 'boolean'],
            'gateways.*.purposes' => ['present', 'array'],
            'gateways.*.purposes.*' => [Rule::in($purposes)],
            'marketplace' => ['sometimes', 'array'],
            'marketplace.commission_percent' => ['sometimes', 'numeric', 'min:0', 'max:50'],
            'marketplace.boost_weekly_price' => ['sometimes', 'numeric', 'min:0', 'max:100000'],
            'marketplace.boost_monthly_price' => ['sometimes', 'numeric', 'min:0', 'max:100000'],
            'marketplace.auto_release_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:30'],
        ]);

        $matrix = [];
        foreach (PaymentGateways::CODES as $code) {
            if (isset($data['gateways'][$code])) {
                $matrix[$code] = [
                    'enabled' => (bool) $data['gateways'][$code]['enabled'],
                    'purposes' => array_values($data['gateways'][$code]['purposes']),
                ];
            }
        }

        PlatformSetting::set(PaymentGateways::SETTING_KEY, array_merge(
            PaymentGateways::matrix(),
            $matrix,
        ));

        if (isset($data['marketplace'])) {
            Marketplace::update($data['marketplace']);
        }

        return response()->json([
            'data' => $this->payload(),
            'message' => __('Payment settings saved.'),
        ]);
    }

    /** Cross-platform gateway transaction register (money console tab). */
    public function transactions(Request $request): JsonResponse
    {
        $this->authorizePlatform($request);

        $query = GatewayTransaction::query()
            ->with('user:id,name,phone')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('gateway'), fn ($q) => $q->where('gateway', $request->string('gateway')))
            ->when($request->filled('purpose'), fn ($q) => $q->where('purpose', $request->string('purpose')))
            ->tap(fn ($q) => SearchTerm::apply($q, $request->string('search')->trim()->value(), fn ($w, string $n) => $w
                ->where('tx_ref', 'ilike', SearchTerm::contains($n))
                ->orWhere('gateway_ref', 'ilike', SearchTerm::contains($n))))
            ->orderByDesc('created_at');

        return response()->json($query->paginate(min((int) $request->input('per_page', 25), 100)));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $matrix = PaymentGateways::matrix();
        $gateways = [];

        foreach ($matrix as $code => $row) {
            $gateways[] = [
                'code' => $code,
                'label' => PaymentGateways::label($code),
                'enabled' => $row['enabled'],
                'configured' => PaymentGateways::configured($code),
                'purposes' => $row['purposes'],
            ];
        }

        return [
            'gateways' => $gateways,
            'purposes' => array_map(fn (GatewayPurpose $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ], GatewayPurpose::cases()),
            'marketplace' => Marketplace::settings(),
        ];
    }

    private function authorizePlatform(Request $request): void
    {
        abort_unless($request->user()?->hasPlatformPermission('gateways.manage'), 403);
    }
}
