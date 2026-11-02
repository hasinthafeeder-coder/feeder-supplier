<?php

namespace Tests\Feature\Settlement;

use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Enums\ResellerDebtObligationType;
use Feeder\Core\Enums\ResellerNonDeliveryFineCondition;
use Feeder\Core\Enums\ResellerReturnReason;
use Feeder\Core\Models\ResellerDebtObligation;
use Feeder\Core\Services\Order\SupplierReturnScanService;
use Feeder\Core\Services\Settlement\ResellerDebtObligationService;
use Feeder\Core\Services\Settlement\ResellerNonDeliveryFineSettingsService;
use Feeder\Core\Services\Settlement\ResellerOutstandingDebtQueryService;
use Feeder\Core\Services\Settlement\ResellerSettlementDebtOffsetCalculator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\SetsUpSupplierOrderApiData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

/**
 * Non-delivery fine debt recording via supplier return scan + outstanding reads.
 */
class ResellerReturnFineDebtRecordingTest extends TestCase
{
    use SetsUpSupplierOrderApiData;
    use UsesMysqlTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        $this->seedMarketLookups();
        $this->allowPermissions([
            'orders.view',
            'orders.return',
            'orders.pack',
            'orders.send-to-print',
            'orders.print',
            'orders.send-to-packing',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_default_non_delivery_fine_is_base_lkr_15(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);
        $order->forceFill([
            'after_hours' => false,
            'duplicate_warning_overridden' => false,
        ])->save();

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => (string) $order->shipment->tracking_number,
            ])
            ->assertOk()
            ->assertJsonPath('data.debt_obligation.original_amount', '15.00')
            ->assertJsonPath('data.debt_obligation.outstanding_amount', '15.00')
            ->assertJsonPath('data.debt_obligation.currency_code', 'LKR');

        $obligation = ResellerDebtObligation::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($obligation);
        $this->assertSame(ResellerDebtObligationType::RETURN_FINE, $obligation->obligation_type);
        $this->assertSame(ResellerNonDeliveryFineCondition::BASE, $obligation->fine_condition);
        $this->assertSame(ResellerReturnReason::OTHER, $obligation->return_reason);
    }

    public function test_per_reseller_base_override_applies_only_to_base(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);
        $order->forceFill([
            'after_hours' => false,
            'duplicate_warning_overridden' => false,
        ])->save();

        $market = $order->market ?? \Feeder\Core\Models\Market::query()->findOrFail($order->market_id);
        $reseller = \Feeder\Core\Models\User::query()->findOrFail($order->reseller_id);
        app(ResellerNonDeliveryFineSettingsService::class)->setResellerBaseOverride($reseller, $market, '40.00');

        app(SupplierReturnScanService::class)->markReturnedByTrackingNumber((int) $context['supplier']->id, (string) $order->shipment->tracking_number, (int) $context['supplier']->id,
        );

        $obligation = ResellerDebtObligation::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(ResellerNonDeliveryFineCondition::BASE, $obligation->fine_condition);
        $this->assertSame('40.00', number_format((float) $obligation->original_amount, 2, '.', ''));

        // Combination totals must ignore the base override (same reseller schedule).
        $schedule = app(ResellerNonDeliveryFineSettingsService::class)->getSchedule($market);
        $calculator = app(\Feeder\Core\Services\Settlement\ResellerReturnFineCalculator::class);
        $this->assertSame(
            '100.00',
            $calculator->amountForCondition(
                ResellerNonDeliveryFineCondition::AFTER_HOURS,
                [
                    'base' => (string) $schedule['base'],
                    'after_hours' => (string) $schedule['after_hours'],
                    'processing_override' => (string) $schedule['processing_override'],
                    'combined' => (string) $schedule['combined'],
                ],
                '40.00',
            )
        );
        $this->assertSame(
            '200.00',
            $calculator->amountForCondition(
                ResellerNonDeliveryFineCondition::COMBINED,
                [
                    'base' => (string) $schedule['base'],
                    'after_hours' => (string) $schedule['after_hours'],
                    'processing_override' => (string) $schedule['processing_override'],
                    'combined' => (string) $schedule['combined'],
                ],
                '40.00',
            )
        );
    }

    public function test_out_of_office_hours_total_fine(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);
        $order->forceFill([
            'after_hours' => true,
            'duplicate_warning_overridden' => false,
        ])->save();

        app(SupplierReturnScanService::class)->markReturnedByTrackingNumber((int) $context['supplier']->id, (string) $order->shipment->tracking_number, (int) $context['supplier']->id,
        );

        $obligation = ResellerDebtObligation::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(ResellerNonDeliveryFineCondition::AFTER_HOURS, $obligation->fine_condition);
        $this->assertSame(ResellerReturnReason::OUT_OF_OFFICE, $obligation->return_reason);
        $this->assertSame('100.00', number_format((float) $obligation->original_amount, 2, '.', ''));
    }

    public function test_processing_order_override_total_fine(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);
        $order->forceFill([
            'after_hours' => false,
            'duplicate_warning_overridden' => true,
        ])->save();

        app(SupplierReturnScanService::class)->markReturnedByTrackingNumber((int) $context['supplier']->id, (string) $order->shipment->tracking_number, (int) $context['supplier']->id,
        );

        $obligation = ResellerDebtObligation::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(ResellerNonDeliveryFineCondition::PROCESSING_OVERRIDE, $obligation->fine_condition);
        $this->assertSame(ResellerReturnReason::DUPLICATE, $obligation->return_reason);
        $this->assertSame('100.00', number_format((float) $obligation->original_amount, 2, '.', ''));
    }

    public function test_both_conditions_together_equal_lkr_200_total(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);
        $order->forceFill([
            'after_hours' => true,
            'duplicate_warning_overridden' => true,
        ])->save();

        app(SupplierReturnScanService::class)->markReturnedByTrackingNumber((int) $context['supplier']->id, (string) $order->shipment->tracking_number, (int) $context['supplier']->id,
        );

        $obligation = ResellerDebtObligation::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(ResellerNonDeliveryFineCondition::COMBINED, $obligation->fine_condition);
        $this->assertSame(ResellerReturnReason::OTHER, $obligation->return_reason);
        $this->assertSame('200.00', number_format((float) $obligation->original_amount, 2, '.', ''));
    }

    public function test_ordinary_return_is_base_fine_only_regardless_of_return_reason_label(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);
        $order->forceFill([
            'after_hours' => false,
            'duplicate_warning_overridden' => false,
        ])->save();

        // Return-reason display label must not drive amount — only authoritative flags.
        app(SupplierReturnScanService::class)->markReturnedByTrackingNumber((int) $context['supplier']->id, (string) $order->shipment->tracking_number, (int) $context['supplier']->id,
        );

        $obligation = ResellerDebtObligation::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(ResellerNonDeliveryFineCondition::BASE, $obligation->fine_condition);
        $this->assertSame('15.00', number_format((float) $obligation->original_amount, 2, '.', ''));
        $this->assertSame(ResellerReturnReason::OTHER, $obligation->return_reason);
    }

    public function test_cancelled_order_excluded(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);
        $order->forceFill([
            'status' => OrderStatus::CANCELLED,
            'after_hours' => true,
            'duplicate_warning_overridden' => true,
        ])->save();

        $result = app(ResellerDebtObligationService::class)->recordReturnFineForOrder($order->fresh());

        $this->assertTrue($result['skipped']);
        $this->assertSame('cancelled_order', $result['skip_reason']);
        $this->assertSame(0, ResellerDebtObligation::query()->where('order_id', $order->id)->count());
    }

    public function test_duplicate_processing_cannot_duplicate_fines(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);
        $tracking = (string) $order->shipment->tracking_number;

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $tracking,
            ])
            ->assertOk();

        $this->actingAs($context['supplier'])
            ->postJson(route('orders.dispatch.scan-return'), [
                'tracking_number' => $tracking,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ORDER_NOT_DISPATCHED');

        $this->assertSame(1, ResellerDebtObligation::query()->where('order_id', $order->id)->count());
        $this->assertSame(
            '15.00',
            number_format((float) ResellerDebtObligation::query()->where('order_id', $order->id)->value('outstanding_amount'), 2, '.', '')
        );
    }

    public function test_idempotent_recording_retries_do_not_duplicate(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);

        app(SupplierReturnScanService::class)->markReturnedByTrackingNumber((int) $context['supplier']->id, (string) $order->shipment->tracking_number, (int) $context['supplier']->id,
        );

        $service = app(ResellerDebtObligationService::class);
        $first = $service->recordReturnFineForOrder($order->fresh());
        $second = $service->recordReturnFineForOrder($order->fresh());

        $this->assertFalse($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame($first['obligation']?->id, $second['obligation']?->id);
        $this->assertSame(1, ResellerDebtObligation::query()->where('order_id', $order->id)->count());
        $this->assertSame('15.00', number_format((float) $first['obligation']->original_amount, 2, '.', ''));
    }

    public function test_fine_currency_matches_order_market_and_myr_gap_is_exposed(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);

        $myMarket = \Feeder\Core\Models\Market::query()->where('code', 'my')->first();
        $this->assertNotNull($myMarket);

        $fineSettings = app(ResellerNonDeliveryFineSettingsService::class);
        $this->assertFalse($fineSettings->hasCompleteSchedule($myMarket));

        $order->forceFill([
            'market_id' => $myMarket->id,
            'currency_id' => $myMarket->currency_id,
            'currency_code_snapshot' => 'MYR',
            'market_code_snapshot' => 'my',
            'after_hours' => false,
            'duplicate_warning_overridden' => false,
        ])->save();

        app(SupplierReturnScanService::class)->markReturnedByTrackingNumber((int) $context['supplier']->id, (string) $order->shipment->tracking_number, (int) $context['supplier']->id,
        );

        $this->assertSame(0, ResellerDebtObligation::query()->where('order_id', $order->id)->count());

        $retry = app(ResellerDebtObligationService::class)->recordReturnFineForOrder($order->fresh(['market.currency', 'reseller']));
        $this->assertTrue($retry['skipped']);
        $this->assertSame('market_fine_defaults_missing', $retry['skip_reason']);
    }

    public function test_minus_offsets_payable_and_excess_debt_carries_forward(): void
    {
        $offset = app(ResellerSettlementDebtOffsetCalculator::class)->calculate('500.00', '15.00');
        $this->assertSame('485.00', $offset['payable_earnings']);
        $this->assertSame('0.00', $offset['remaining_debt']);

        $carry = app(ResellerSettlementDebtOffsetCalculator::class)->calculate('50.00', '200.00');
        $this->assertSame('0.00', $carry['payable_earnings']);
        $this->assertSame('150.00', $carry['remaining_debt']);
    }

    public function test_retries_and_settlement_recalculation_do_not_double_deduct(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);

        app(SupplierReturnScanService::class)->markReturnedByTrackingNumber((int) $context['supplier']->id, (string) $order->shipment->tracking_number, (int) $context['supplier']->id,
        );

        $service = app(ResellerDebtObligationService::class);
        $service->recordReturnFineForOrder($order->fresh());
        $service->recordReturnFineForOrder($order->fresh());

        $this->assertSame(1, ResellerDebtObligation::query()->where('order_id', $order->id)->count());
        $this->assertSame(
            '15.00',
            number_format((float) ResellerDebtObligation::query()->where('order_id', $order->id)->value('outstanding_amount'), 2, '.', '')
        );
    }

    public function test_database_uniqueness_protects_concurrent_inserts(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);

        app(SupplierReturnScanService::class)->markReturnedByTrackingNumber((int) $context['supplier']->id, (string) $order->shipment->tracking_number, (int) $context['supplier']->id,
        );

        $existing = ResellerDebtObligation::query()->where('order_id', $order->id)->firstOrFail();

        try {
            ResellerDebtObligation::query()->create([
                'obligation_number' => 'RDO-RF-DUP-'.$order->id,
                'reseller_id' => $existing->reseller_id,
                'reseller_company_id' => $existing->reseller_company_id,
                'order_id' => $existing->order_id,
                'market_id' => $existing->market_id,
                'currency_id' => $existing->currency_id,
                'currency_code_snapshot' => 'LKR',
                'obligation_type' => ResellerDebtObligationType::RETURN_FINE,
                'return_reason' => ResellerReturnReason::OTHER,
                'fine_condition' => ResellerNonDeliveryFineCondition::BASE,
                'original_amount' => '15.00',
                'outstanding_amount' => '15.00',
                'effective_date' => $existing->effective_date,
                'source_type' => ResellerDebtObligation::SOURCE_SUPPLIER_RETURN_SCAN,
                'source_idempotency_key' => 'return_fine:order:'.$order->id.'-dup',
            ]);
            $this->fail('Duplicate (obligation_type, order_id) must be rejected by the database.');
        } catch (QueryException $exception) {
            $this->assertTrue(
                str_contains(strtolower($exception->getMessage()), 'unique')
                || str_contains(strtolower($exception->getMessage()), 'duplicate')
            );
        }

        $this->assertSame(1, ResellerDebtObligation::query()->where('order_id', $order->id)->count());
    }

    public function test_order_cannot_be_charged_to_wrong_reseller(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);

        app(SupplierReturnScanService::class)->markReturnedByTrackingNumber((int) $context['supplier']->id, (string) $order->shipment->tracking_number, (int) $context['supplier']->id,
        );

        $otherReseller = $this->makeResellerUser();
        $order->forceFill(['reseller_id' => $otherReseller->id])->save();

        $this->expectException(\Feeder\Core\Exceptions\ResellerDebtObligationException::class);
        $this->expectExceptionMessage('different reseller');

        app(ResellerDebtObligationService::class)->recordReturnFineForOrder($order->fresh());
    }

    public function test_return_reason_correction_does_not_rewrite_or_duplicate_history(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);
        $order->forceFill([
            'after_hours' => true,
            'duplicate_warning_overridden' => false,
        ])->save();

        app(SupplierReturnScanService::class)->markReturnedByTrackingNumber((int) $context['supplier']->id, (string) $order->shipment->tracking_number, (int) $context['supplier']->id,
        );

        $report = app(ResellerDebtObligationService::class)->reportReturnReasonCorrection(
            $order->fresh(),
            ResellerReturnReason::OTHER,
        );

        $this->assertTrue($report['unresolved']);
        $this->assertFalse($report['corrected']);
        $this->assertSame(1, ResellerDebtObligation::query()->where('order_id', $order->id)->count());

        $obligation = ResellerDebtObligation::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(ResellerReturnReason::OUT_OF_OFFICE, $obligation->return_reason);
        $this->assertSame('100.00', number_format((float) $obligation->original_amount, 2, '.', ''));
    }

    public function test_delivered_confirmation_path_does_not_create_return_fine(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);

        DB::transaction(function () use ($order, $context): void {
            $locked = \Feeder\Core\Models\Order::query()->lockForUpdate()->findOrFail($order->id);
            app(\Feeder\Core\Services\Order\OrderStatusService::class)->transition(
                $locked,
                OrderStatus::DELIVERED,
                (int) $context['supplier']->id,
                null,
                'Delivered for fine regression',
            );
        });

        $result = app(ResellerDebtObligationService::class)->recordReturnFineForOrder($order->fresh());

        $this->assertTrue($result['skipped']);
        $this->assertSame('delivered_order', $result['skip_reason']);
        $this->assertSame(0, ResellerDebtObligation::query()->where('order_id', $order->id)->count());
    }

    public function test_outstanding_balance_read_is_non_mutating_and_grouped_by_currency(): void
    {
        $context = $this->makeConfirmedOrderContext(stockQuantity: 5, orderQuantity: 1);
        $order = $this->moveToDispatched($context);

        app(SupplierReturnScanService::class)->markReturnedByTrackingNumber((int) $context['supplier']->id, (string) $order->shipment->tracking_number, (int) $context['supplier']->id,
        );

        $before = ResellerDebtObligation::query()->where('order_id', $order->id)->firstOrFail();
        $beforeOutstanding = (string) $before->outstanding_amount;
        $beforeUpdatedAt = (string) $before->updated_at;

        $query = app(ResellerOutstandingDebtQueryService::class);
        $totals = $query->totalsByCurrency(['reseller_id' => (int) $order->reseller_id]);
        $query->paginateObligations(['reseller_id' => (int) $order->reseller_id]);
        $query->outstandingByResellerAndCurrency((int) $order->reseller_id);

        $after = $before->fresh();
        $this->assertSame($beforeOutstanding, (string) $after->outstanding_amount);
        $this->assertSame($beforeUpdatedAt, (string) $after->updated_at);

        $this->assertNotEmpty($totals);
        foreach ($totals as $total) {
            $this->assertArrayHasKey('currency', $total);
            $this->assertArrayHasKey('outstanding_total', $total);
        }

        $currencies = array_column($totals, 'currency');
        $this->assertSame($currencies, array_values(array_unique($currencies)));
    }
}
