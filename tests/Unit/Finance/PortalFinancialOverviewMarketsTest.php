<?php

namespace Tests\Unit\Finance;

use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Currency;
use Feeder\Core\Models\Market;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\User;
use Feeder\Core\Services\MarketService;
use Feeder\Core\Services\PortalFinancialOverviewMarkets;
use Illuminate\Database\Eloquent\Collection;
use ReflectionMethod;
use Tests\TestCase;

class PortalFinancialOverviewMarketsTest extends TestCase
{
    public function test_active_markets_come_from_the_existing_is_active_query(): void
    {
        $method = new ReflectionMethod(MarketService::class, 'listActiveMarkets');
        $body = implode('', array_slice(
            file($method->getFileName()),
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1,
        ));

        $this->assertStringContainsString('Market::query()', $body);
        $this->assertStringContainsString("where('is_active', true)", $body);
        $this->assertStringNotContainsString('supplier_settlement', $body);
        $this->assertStringNotContainsString('reseller_settlement', $body);
    }

    public function test_sri_lanka_only_returns_the_active_sri_lanka_market(): void
    {
        $markets = $this->catalog([
            $this->market('lk', 'Sri Lanka', 'LKR'),
        ])->active();

        $this->assertSame(['lk'], $markets->pluck('code')->all());
        $this->assertSame('LKR', $markets->first()->currency->iso_code);
    }

    public function test_malaysia_only_returns_the_active_malaysia_market(): void
    {
        $markets = $this->catalog([
            $this->market('my', 'Malaysia', 'MYR'),
        ])->active();

        $this->assertSame(['my'], $markets->pluck('code')->all());
        $this->assertSame('MYR', $markets->first()->currency->iso_code);
    }

    public function test_both_active_markets_are_ordered_sri_lanka_then_malaysia(): void
    {
        $markets = $this->catalog([
            $this->market('my', 'Malaysia', 'MYR'),
            $this->market('lk', 'Sri Lanka', 'LKR'),
        ])->active();

        $this->assertSame(['lk', 'my'], $markets->pluck('code')->all());
    }

    public function test_neither_configured_market_returns_an_empty_list(): void
    {
        $markets = $this->catalog([
            $this->market('th', 'Thailand', 'THB'),
        ])->active();

        $this->assertTrue($markets->isEmpty());
    }

    public function test_supplier_actor_only_sees_their_operation_market(): void
    {
        $lk = $this->market('lk', 'Sri Lanka', 'LKR', 11);
        $my = $this->market('my', 'Malaysia', 'MYR', 12);
        $service = $this->catalog([$lk, $my]);

        $supplier = $this->party(PortalCode::SUPPLIER, operationMarket: $lk);

        $this->assertSame(['lk'], $service->forActor($supplier)->pluck('code')->all());
    }

    public function test_reseller_actor_only_sees_allowed_markets(): void
    {
        $lk = $this->market('lk', 'Sri Lanka', 'LKR', 21);
        $my = $this->market('my', 'Malaysia', 'MYR', 22);
        $service = $this->catalog([$lk, $my]);

        $lkOnly = $this->party(PortalCode::RESELLER, allowedMarkets: [$lk]);
        $both = $this->party(PortalCode::RESELLER, allowedMarkets: [$my, $lk]);

        $this->assertSame(['lk'], $service->forActor($lkOnly)->pluck('code')->all());
        $this->assertSame(['lk', 'my'], $service->forActor($both)->pluck('code')->all());
    }

    public function test_unregistered_actor_sees_no_market_cards(): void
    {
        $service = $this->catalog([
            $this->market('lk', 'Sri Lanka', 'LKR', 31),
            $this->market('my', 'Malaysia', 'MYR', 32),
        ]);

        $supplier = $this->party(PortalCode::SUPPLIER, operationMarket: null);
        $reseller = $this->party(PortalCode::RESELLER, allowedMarkets: []);

        $this->assertTrue($service->forActor($supplier)->isEmpty());
        $this->assertTrue($service->forActor($reseller)->isEmpty());
    }

    public function test_supplier_cards_follow_the_dashboard_icon_and_one_currency_per_market(): void
    {
        $dashboard = (string) file_get_contents(resource_path('views/pages/main/dashboard.blade.php'));
        $icon = 'width: 75px; height: 75px; line-height: 105px; background-color: #EF4923;';

        $this->assertStringContainsString($icon, $dashboard);
        $this->assertStringContainsString('style="color: #FFFFFF;"', $dashboard);

        $html = view('pages.finance.partials.market-summary', [
            'overviewMarkets' => collect([
                $this->market('lk', 'Sri Lanka', 'LKR'),
                $this->market('my', 'Malaysia', 'MYR'),
            ]),
            'cards' => $this->supplierCards(),
        ])->render();

        $this->assertStringContainsString($icon, $html);
        $this->assertStringContainsString('style="color: #FFFFFF;"', $html);
        $this->assertStringContainsString('account_balance_wallet', $html);
        $this->assertStringContainsString('payments', $html);
        $this->assertStringContainsString('pending', $html);
        $this->assertStringContainsString('today', $html);
        $this->assertStringNotContainsString('theme-basic', $html);
        $this->assertSame(8, substr_count($html, 'finance-today-card'));
        $this->assertSame(8, substr_count($html, 'Pending integration'));

        $sriLanka = $this->section($html, 'financial-overview-market-lk');
        $malaysia = $this->section($html, 'financial-overview-market-my');

        $this->assertStringContainsString('Sri Lanka', $sriLanka);
        $this->assertStringContainsString('LKR', $sriLanka);
        $this->assertStringNotContainsString('MYR', $sriLanka);
        $this->assertSame(4, substr_count($sriLanka, 'finance-today-card'));

        $this->assertStringContainsString('Malaysia', $malaysia);
        $this->assertStringContainsString('MYR', $malaysia);
        $this->assertStringNotContainsString('LKR', $malaysia);
        $this->assertSame(4, substr_count($malaysia, 'finance-today-card'));
    }

    public function test_supplier_cards_are_omitted_when_no_market_is_active(): void
    {
        $html = view('pages.finance.partials.market-summary', [
            'overviewMarkets' => collect(),
            'cards' => $this->supplierCards(),
        ])->render();

        $this->assertStringContainsString('financialOverviewMarketsEmpty', $html);
        $this->assertStringContainsString('No eligible Sri Lanka or Malaysia market is available for your account.', $html);
        $this->assertStringNotContainsString('finance-today-card', $html);
        $this->assertStringNotContainsString('Pending integration', $html);
    }

    public function test_supplier_cards_render_only_the_active_market_section(): void
    {
        $html = view('pages.finance.partials.market-summary', [
            'overviewMarkets' => collect([$this->market('my', 'Malaysia', 'MYR')]),
            'cards' => $this->supplierCards(),
        ])->render();

        $this->assertStringContainsString('id="financial-overview-market-my"', $html);
        $this->assertStringNotContainsString('id="financial-overview-market-lk"', $html);
        $this->assertSame(4, substr_count($html, 'finance-today-card'));
        $this->assertStringNotContainsString('LKR', $html);
    }

    /**
     * @param  list<Market>  $markets
     */
    private function catalog(array $markets): PortalFinancialOverviewMarkets
    {
        $service = $this->createMock(MarketService::class);
        $service->expects($this->atLeastOnce())
            ->method('listActiveMarkets')
            ->willReturn(new Collection($markets));

        return new PortalFinancialOverviewMarkets($service);
    }

    private function market(string $code, string $name, string $currency, ?int $id = null): Market
    {
        $market = new Market([
            'code' => $code,
            'name' => $name,
            'is_active' => true,
        ]);
        if ($id !== null) {
            $market->id = $id;
        }
        $market->setRelation('currency', new Currency(['iso_code' => $currency]));

        return $market;
    }

    /**
     * @param  list<Market>  $allowedMarkets
     */
    private function party(
        PortalCode $portal,
        ?Market $operationMarket = null,
        array $allowedMarkets = [],
    ): User {
        $portalModel = new Portal([
            'code' => $portal->value,
            'name' => $portal->name,
            'is_active' => true,
        ]);

        $company = new Company([
            'name' => $portal->value.' Co',
            'operation_market_id' => $operationMarket?->id,
        ]);
        $company->setRelation('portal', $portalModel);
        $company->setRelation('operationMarket', $operationMarket);
        $company->setRelation('allowedMarkets', new Collection($allowedMarkets));

        $user = new User([
            'email' => strtolower($portal->value).'@feeder.test',
        ]);
        $user->setRelation('company', $company);

        return $user;
    }

    /**
     * @return list<array{id: string, title: string, icon: string, caption: string, foot: string}>
     */
    private function supplierCards(): array
    {
        return [
            ['id' => 'supplier-total-payable-card', 'title' => 'Total payable', 'icon' => 'account_balance_wallet', 'caption' => 'Payable', 'foot' => 'Your account'],
            ['id' => 'supplier-total-settled-card', 'title' => 'Total settled/paid', 'icon' => 'payments', 'caption' => 'Paid', 'foot' => 'Cash payments only'],
            ['id' => 'supplier-outstanding-card', 'title' => 'Outstanding balance', 'icon' => 'pending', 'caption' => 'Outstanding', 'foot' => 'By currency'],
            ['id' => 'supplier-today-payable-card', 'title' => "Today's payable", 'icon' => 'today', 'caption' => 'Today', 'foot' => "Today's settlement date"],
        ];
    }

    private function section(string $html, string $id): string
    {
        $start = strpos($html, 'id="'.$id.'"');
        $this->assertNotFalse($start);

        $next = strpos($html, '<section', $start + 1);

        return $next === false ? substr($html, $start) : substr($html, $start, $next - $start);
    }
}
