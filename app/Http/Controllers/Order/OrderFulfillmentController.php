<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\SendToPackagingRequest;
use App\Http\Requests\Order\SendToPrintRequest;
use Feeder\Core\Exceptions\SupplierFulfillmentException;
use Feeder\Core\Services\Order\SupplierFulfillmentBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OrderFulfillmentController extends Controller
{
    public function __construct(
        private readonly SupplierFulfillmentBatchService $batchService,
    ) {
    }

    public function sendToPrint(SendToPrintRequest $request): JsonResponse
    {
        try {
            $orders = $this->batchService->sendToPrintBatch(
                (int) Auth::id(),
                $request->selectionPayload(),
                (int) Auth::id(),
            );
        } catch (SupplierFulfillmentException $exception) {
            return $exception->toResponse();
        }

        return response()->json([
            'message' => 'Orders sent to print.',
            'data' => $orders->map(fn ($order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'fulfillment_status' => $order->fulfillment?->status?->value,
                'invoice_number' => $order->fulfillment?->invoice_number,
            ])->values()->all(),
        ]);
    }

    public function sendToPackaging(SendToPackagingRequest $request): JsonResponse
    {
        try {
            $orders = $this->batchService->sendToPackagingBatch(
                (int) Auth::id(),
                $request->orderIds(),
                (int) Auth::id(),
            );
        } catch (SupplierFulfillmentException $exception) {
            return $exception->toResponse();
        }

        return response()->json([
            'message' => 'Orders sent to packaging.',
            'data' => $orders->map(fn ($order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'fulfillment_status' => $order->fulfillment?->status?->value,
            ])->values()->all(),
        ]);
    }
}
