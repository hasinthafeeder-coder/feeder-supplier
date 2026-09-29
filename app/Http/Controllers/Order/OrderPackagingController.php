<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CancelOrderRequest;
use App\Http\Requests\Order\CompletePackageRequest;
use App\Http\Requests\Order\ScanOrderRequest;
use App\Http\Requests\Order\ScanProductBarcodeRequest;
use Feeder\Core\Exceptions\SupplierFulfillmentException;
use Feeder\Core\Models\Order;
use Feeder\Core\Services\Order\SupplierFulfillmentService;
use Feeder\Core\Services\Order\SupplierOrderApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class OrderPackagingController extends Controller
{
    public function __construct(
        private readonly SupplierOrderApiService $orderApiService,
        private readonly SupplierFulfillmentService $fulfillmentService,
    ) {
    }

    public function scanOrder(ScanOrderRequest $request): JsonResponse
    {
        try {
            $data = $this->orderApiService->loadPackagingByOrderReference(
                (int) Auth::id(),
                $request->string('order_reference')->toString(),
            );
        } catch (SupplierFulfillmentException $exception) {
            return $exception->toResponse();
        }

        return response()->json(['data' => $data]);
    }

    public function scanProduct(ScanProductBarcodeRequest $request): JsonResponse
    {
        try {
            $data = $this->orderApiService->scanProductBarcode(
                (int) Auth::id(),
                (int) $request->input('order_id'),
                $request->string('product_barcode')->toString(),
                (int) Auth::id(),
            );
        } catch (SupplierFulfillmentException $exception) {
            return $exception->toResponse();
        }

        return response()->json([
            'message' => 'Product scanned.',
            'data' => $data,
        ]);
    }

    public function complete(CompletePackageRequest $request): JsonResponse
    {
        try {
            $data = $this->orderApiService->completePackage(
                (int) Auth::id(),
                (int) $request->input('order_id'),
                (int) Auth::id(),
            );
        } catch (SupplierFulfillmentException $exception) {
            return $exception->toResponse();
        }

        return response()->json([
            'message' => 'Package completed.',
            'data' => $data,
        ]);
    }

    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        $supplierId = (int) Auth::id();

        if ((int) $order->supplier_id !== $supplierId) {
            return (new SupplierFulfillmentException(
                'SUPPLIER_NOT_AUTHORIZED',
                'Order not found.',
                status: 404,
            ))->toResponse();
        }

        try {
            $cancelled = $this->fulfillmentService->cancel(
                $order,
                $supplierId,
                $supplierId,
                $request->input('reason'),
            );
        } catch (ValidationException $exception) {
            $message = (string) (collect($exception->errors())->flatten()->first() ?? 'Unable to cancel order.');
            $lower = strtolower($message);

            $code = 'ORDER_NOT_FRESH';
            if (str_contains($lower, 'packaging')) {
                $code = 'ORDER_NOT_PACKAGING';
            } elseif (str_contains($lower, 'dispatch')) {
                $code = 'ALREADY_DISPATCHED';
            }

            return (new SupplierFulfillmentException($code, $message, $exception->errors()))->toResponse();
        }

        return response()->json([
            'message' => 'Order cancelled.',
            'data' => [
                'id' => $cancelled->id,
                'order_number' => $cancelled->order_number,
                'status' => $cancelled->status?->value,
            ],
        ]);
    }
}
