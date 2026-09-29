<?php

namespace Tests\Support;

use App\Models\User;
use Feeder\Core\Authorization\Services\PermissionService;
use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\OrderSource;
use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Enums\OrderType;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Enums\ShipmentStatus;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Courier;
use Feeder\Core\Models\CourierCity;
use Feeder\Core\Models\CourierMarketPricing;
use Feeder\Core\Models\CourierService;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\ProductCategory;
use Feeder\Core\Models\ProductVariant;
use Feeder\Core\Models\ResellerSupplierAssignment;
use Feeder\Core\Models\Shipment;
use Feeder\Core\Models\SupplierCourierAccount;
use Feeder\Core\Services\GoodsReceivedNoteService;
use Feeder\Core\Services\Order\OrderService;
use Feeder\Core\Services\Order\OrderStatusService;
use Feeder\Core\Services\Order\SupplierFulfillmentService;
use Feeder\Core\Services\UuidService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;

trait SetsUpSupplierOrderApiData
{
    use SetsUpMarketData;

    /**
     * @var list<string>
     */
    private array $allowedPermissions = [];

    /**
     * @param  list<string>  $permissions
     */
    protected function allowPermissions(array $permissions): void
    {
        $this->allowedPermissions = $permissions;

        $permissionService = Mockery::mock(PermissionService::class);
        $permissionService->shouldReceive('hasPermission')
            ->andReturnUsing(function ($user, string $permission): bool {
                return in_array($permission, $this->allowedPermissions, true);
            });
        $permissionService->shouldReceive('hasAnyPermission')
            ->andReturnUsing(function ($user, array $permissions): bool {
                return collect($permissions)->intersect($this->allowedPermissions)->isNotEmpty();
            });
        $permissionService->shouldReceive('hasAllPermissions')
            ->andReturnUsing(function ($user, array $permissions): bool {
                return collect($permissions)->diff($this->allowedPermissions)->isEmpty();
            });

        $this->app->instance(PermissionService::class, $permissionService);
    }

    protected function makeSupplierUser(string $companyName = 'Supplier Orders Co'): User
    {
        $portal = Portal::query()->firstOrCreate(
            ['code' => PortalCode::SUPPLIER->value],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Supplier Portal',
                'subdomain' => 'supplier-'.Str::lower(Str::random(4)),
                'description' => 'Supplier Portal',
                'is_active' => true,
            ]
        );

        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'portal_id' => $portal->id,
            'name' => $companyName,
            'email' => Str::lower(Str::slug($companyName)).'-'.Str::lower(Str::random(4)).'@feeder.local',
            'phone' => '077'.random_int(1000000, 9999999),
            'status' => CompanyStatus::ACTIVE->value,
            'operation_market_id' => $this->marketByCode('lk')->id,
        ]);

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'email' => $company->email,
            'phone' => $company->phone,
            'password' => Hash::make('password'),
            'user_type' => UserType::OWNER->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
        ]);

        $company->forceFill(['owner_user_id' => $user->id])->save();
        $this->configureSupplierCompany($company, 'lk');

        return $user->fresh(['company']);
    }

    protected function makeResellerUser(): User
    {
        $portal = Portal::query()->firstOrCreate(
            ['code' => PortalCode::RESELLER->value],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Reseller Portal',
                'subdomain' => 'reseller-'.Str::lower(Str::random(4)),
                'description' => 'Reseller Portal',
                'is_active' => true,
            ]
        );

        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'portal_id' => $portal->id,
            'name' => 'Reseller '.Str::upper(Str::random(4)),
            'email' => 'reseller-'.Str::lower(Str::random(6)).'@feeder.local',
            'phone' => '078'.random_int(1000000, 9999999),
            'status' => CompanyStatus::ACTIVE->value,
            'home_country_id' => $this->countryByIso('LK')->id,
        ]);

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'email' => $company->email,
            'phone' => $company->phone,
            'password' => Hash::make('password'),
            'user_type' => UserType::OWNER->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
        ]);

        $company->forceFill(['owner_user_id' => $user->id])->save();
        $this->configureResellerCompany($company, ['lk']);

        return $user->fresh(['company']);
    }

    protected function assignSupplierToReseller(User $reseller, User $supplier): void
    {
        ResellerSupplierAssignment::query()->firstOrCreate([
            'reseller_id' => $reseller->id,
            'supplier_id' => $supplier->id,
        ], [
            'uuid' => UuidService::generate(),
            'assigned_by' => $reseller->id,
        ]);
    }

    /**
     * @return array{product: Product, variant: ProductVariant}
     */
    protected function makeSupplierProductVariant(User $supplier): array
    {
        $category = ProductCategory::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'General',
            'slug' => 'general-'.Str::lower(Str::random(6)),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'market_id' => $this->marketByCode('lk')->id,
            'name' => 'Product '.Str::upper(Str::random(5)),
            'slug' => 'product-'.Str::lower(Str::random(8)),
            'status' => ProductStatus::ACTIVE->value,
            'system_visible' => true,
            'web_visible' => true,
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ]);

        $variant = ProductVariant::query()->create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'name' => 'Standard',
            'barcode' => '',
            'cost' => 100,
            'selling_price' => 250,
            'suggested_price' => 300,
            'weight' => 0.5,
            'company_commission' => 25,
            'sort_order' => 0,
            'is_active' => true,
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ]);

        return compact('product', 'variant');
    }

    protected function seedStock(User $supplier, int $productId, int $variantId, int $received): void
    {
        app(GoodsReceivedNoteService::class)->createGrn(
            $supplier->id,
            [
                'received_date' => now()->toDateString(),
                'created_by' => $supplier->id,
                'updated_by' => $supplier->id,
            ],
            [[
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'received_quantity' => $received,
                'damaged_quantity' => 0,
                'unit_cost' => '100.00',
            ]]
        );
    }

    protected function createAndConfirmOrder(
        User $reseller,
        User $supplier,
        int $variantId,
        int $quantity,
        OrderType $orderType = OrderType::NEW,
    ): Order {
        return $this->createAndConfirmOrderWithItems(
            $reseller,
            $supplier,
            [[
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
                'unit_selling_price' => 250,
            ]],
            $orderType,
        );
    }

    /**
     * @param  list<array{product_variant_id: int, quantity: int, unit_selling_price?: float|int}>  $items
     */
    protected function createAndConfirmOrderWithItems(
        User $reseller,
        User $supplier,
        array $items,
        OrderType $orderType = OrderType::NEW,
    ): Order {
        $countryId = $this->countryByIso('LK')->id;
        $phone = '070'.random_int(1000000, 9999999);

        $payloadItems = array_map(static function (array $item): array {
            return [
                'product_variant_id' => (int) $item['product_variant_id'],
                'quantity' => (int) $item['quantity'],
                'unit_selling_price' => $item['unit_selling_price'] ?? 250,
            ];
        }, $items);

        $order = app(OrderService::class)->create([
            'source' => OrderSource::MANUAL,
            'order_type' => $orderType,
            'market_id' => $this->marketByCode('lk')->id,
            'reseller_id' => $reseller->id,
            'reseller_company_id' => $reseller->company_id,
            'supplier_id' => $supplier->id,
            'created_by' => $reseller->id,
            'customer' => [
                'display_name' => 'API Customer',
                'primary_country_id' => $countryId,
                'primary_phone' => $phone,
                'primary_phone_country_id' => $countryId,
            ],
            'address' => [
                'recipient_name' => 'API Customer',
                'line1' => '12 Test Street',
                'city_name' => 'Colombo',
                'district_name' => 'Colombo',
                'country_id' => $countryId,
                'full_address_text' => '12 Test Street, Colombo',
            ],
            'items' => $payloadItems,
            'discount_amount' => 0,
            'courier_fee_amount' => 350,
        ]);

        return app(OrderStatusService::class)->transition(
            $order,
            OrderStatus::CONFIRMED,
            $reseller->id,
        )->fresh(['items.variant', 'fulfillment', 'address', 'resellerCompany', 'supplier.company']);
    }

    /**
     * @return array{
     *     order: Order,
     *     supplier: User,
     *     reseller: User,
     *     variant: ProductVariant,
     *     product: Product
     * }
     */
    protected function makeConfirmedOrderContext(
        int $stockQuantity = 10,
        int $orderQuantity = 1,
        OrderType $orderType = OrderType::NEW,
        ?User $supplier = null,
    ): array {
        $reseller = $this->makeResellerUser();
        $supplier ??= $this->makeSupplierUser();
        $this->assignSupplierToReseller($reseller, $supplier);
        $catalog = $this->makeSupplierProductVariant($supplier);
        $variant = $catalog['variant'];
        $product = $catalog['product'];

        if ($stockQuantity > 0) {
            $this->seedStock($supplier, $product->id, $variant->id, $stockQuantity);
        }

        $order = $this->createAndConfirmOrder(
            $reseller,
            $supplier,
            $variant->id,
            $orderQuantity,
            $orderType,
        );

        return compact('order', 'supplier', 'reseller', 'variant', 'product');
    }

    protected function ensurePrintableShipment(Order $order): Shipment
    {
        $order->loadMissing('shipment');

        if ($order->shipment !== null) {
            return $order->shipment;
        }

        $courier = Courier::query()->create([
            'code' => 'DOM'.strtoupper(substr(uniqid(), -4)),
            'name' => 'Domestic Courier',
            'is_active' => true,
        ]);

        $service = CourierService::query()->create([
            'courier_id' => $courier->id,
            'code' => 'COD',
            'name' => 'Cash on Delivery',
            'external_service_id' => 'ext-cod',
            'is_active' => true,
        ]);

        $city = CourierCity::query()->create([
            'courier_id' => $courier->id,
            'district_name' => 'Colombo',
            'city_name' => 'Colombo 03',
            'external_city_code' => 'CMB03',
            'external_district_code' => 'COL',
            'is_active' => true,
        ]);

        CourierMarketPricing::query()->create([
            'courier_id' => $courier->id,
            'market_id' => $order->market_id,
            'currency_id' => $order->currency_id,
            'first_kg_fee' => 600.00,
            'additional_kg_fee' => 100.00,
            'is_active' => true,
        ]);

        $hasDefaultAccount = SupplierCourierAccount::query()
            ->where('supplier_id', $order->supplier_id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->exists();

        $account = SupplierCourierAccount::query()->create([
            'supplier_id' => $order->supplier_id,
            'courier_id' => $courier->id,
            'account_label' => 'Primary Account',
            'credentials_encrypted' => Crypt::encryptString(json_encode(['api_key' => 'secret'], JSON_THROW_ON_ERROR)),
            'is_active' => true,
            'is_default' => ! $hasDefaultAccount,
        ]);

        return Shipment::query()->create([
            'order_id' => $order->id,
            'supplier_id' => $order->supplier_id,
            'courier_id' => $courier->id,
            'courier_service_id' => $service->id,
            'courier_city_id' => $city->id,
            'supplier_courier_account_id' => $account->id,
            'tracking_number' => 'TRK-'.strtoupper(substr(uniqid(), -6)),
            'weight_snapshot' => 1,
            'courier_fee_snapshot' => 350,
            'currency_id' => $order->currency_id,
            'status' => ShipmentStatus::BOOKED,
            'booked_at' => now(),
            'booked_by' => $order->reseller_id,
        ]);
    }

    protected function moveToPrint(array $context): Order
    {
        app(SupplierFulfillmentService::class)->sendToPrint(
            $context['order'],
            $context['supplier']->id,
        );

        return $context['order']->fresh(['fulfillment', 'items.variant', 'shipment']);
    }

    protected function moveToPackaging(array $context): Order
    {
        $order = $this->moveToPrint($context);
        $this->ensurePrintableShipment($order);
        app(SupplierFulfillmentService::class)->markPrinted(
            $order->fresh(['shipment', 'fulfillment']),
            $context['supplier']->id,
        );
        app(SupplierFulfillmentService::class)->sendToPackaging(
            $order->fresh(),
            $context['supplier']->id,
        );

        return $order->fresh([
            'fulfillment.items',
            'items.variant',
            'shipment.courier',
            'address',
        ]);
    }
}
