<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\PreparePrintDataRequest;
use Feeder\Core\Exceptions\SupplierFulfillmentException;
use Feeder\Core\Services\Order\SupplierOrderApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OrderPrintController extends Controller
{
    public function __construct(
        private readonly SupplierOrderApiService $orderApiService,
    ) {
    }

    public function prepare(PreparePrintDataRequest $request): JsonResponse
    {
        try {
            $payloads = $this->orderApiService->preparePrintData(
                (int) Auth::id(),
                $request->orderIds(),
                (int) Auth::id(),
            );
        } catch (SupplierFulfillmentException $exception) {
            return $exception->toResponse();
        }

        return response()->json([
            'message' => 'Print data prepared.',
            'data' => $payloads,
        ]);
    }

    public function reprint(PreparePrintDataRequest $request): JsonResponse
    {
        try {
            $payloads = $this->orderApiService->prepareReprintData(
                (int) Auth::id(),
                $request->orderIds(),
            );
        } catch (SupplierFulfillmentException $exception) {
            return $exception->toResponse();
        }

        return response()->json([
            'message' => 'Invoice ready for re-printing.',
            'data' => $payloads,
        ]);
    }
}
