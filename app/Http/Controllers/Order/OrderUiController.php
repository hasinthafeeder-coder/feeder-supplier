<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use Feeder\Core\Exceptions\SupplierFulfillmentException;
use Feeder\Core\Models\Order;
use Feeder\Core\Services\Order\SupplierFulfillmentBatchService;
use Feeder\Core\Services\Order\SupplierOrderApiService;
use Feeder\Core\Support\PackagingBarcode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderUiController extends Controller
{
    public function __construct(
        private readonly SupplierOrderApiService $orderApiService,
    ) {
    }

    public function newOrders(Request $request): View
    {
        $mode = (string) $request->query('mode', 'fresh');
        $allowedModes = ['fresh', 'exchange', 'out-of-stock'];

        if (! in_array($mode, $allowedModes, true)) {
            $mode = 'fresh';
        }

        $modeCounts = [
            'fresh' => 0,
            'exchange' => 0,
            'out_of_stock' => 0,
        ];

        $userId = Auth::id();
        if ($userId !== null) {
            $modeCounts = $this->orderApiService->newOrdersModeCounts((int) $userId);
        }

        return view('pages.orders.new', array_merge($this->pagePermissions(), [
            'initialMode' => $mode,
            'modeCounts' => $modeCounts,
        ]));
    }

    public function print(): View
    {
        return view('pages.orders.print', array_merge($this->pagePermissions(), [
            'maxBatchSize' => SupplierFulfillmentBatchService::MAX_BATCH_SIZE,
        ]));
    }

    public function packaging(): View
    {
        return view('pages.orders.packaging', array_merge($this->pagePermissions(), [
            'completePackageBarcode' => PackagingBarcode::COMPLETE_PACKAGE,
            'completePackageBarcodeAliases' => PackagingBarcode::acceptedValues(),
        ]));
    }

    public function show(Order $order): View
    {
        $supplierId = (int) Auth::id();

        try {
            $orderData = $this->orderApiService->showOrder($supplierId, $order);
        } catch (SupplierFulfillmentException $exception) {
            abort($exception->status, $exception->getMessage());
        }

        return view('pages.orders.show', array_merge($this->pagePermissions(), [
            'orderData' => $orderData,
            'order' => $order,
        ]));
    }

    /**
     * @return array{
     *     canView: bool,
     *     canSendToPrint: bool,
     *     canCancel: bool,
     *     canPrint: bool,
     *     canSendToPacking: bool,
     *     canPack: bool
     * }
     */
    private function pagePermissions(): array
    {
        $user = Auth::user();

        return [
            'canView' => $user?->hasPermission('orders.view') === true,
            'canSendToPrint' => $user?->hasPermission('orders.send-to-print') === true,
            'canCancel' => $user?->hasPermission('orders.cancel') === true,
            'canPrint' => $user?->hasPermission('orders.print') === true,
            'canSendToPacking' => $user?->hasPermission('orders.send-to-packing') === true,
            'canPack' => $user?->hasPermission('orders.pack') === true,
        ];
    }
}
