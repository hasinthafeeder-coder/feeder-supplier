<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ScanReturnedOrderRequest;
use Feeder\Core\Exceptions\SupplierFulfillmentException;
use Feeder\Core\Services\Order\SupplierReturnScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OrderReturnController extends Controller
{
    public function __construct(
        private readonly SupplierReturnScanService $returnScanService,
    ) {
    }

    public function scanReturn(ScanReturnedOrderRequest $request): JsonResponse
    {
        $isDamaged = $request->boolean('is_damaged');

        try {
            $data = $this->returnScanService->markReturnedByTrackingNumber(
                (int) Auth::id(),
                $request->string('tracking_number')->toString(),
                (int) Auth::id(),
                $isDamaged,
            );
        } catch (SupplierFulfillmentException $exception) {
            return $exception->toResponse();
        }

        $message = $isDamaged
            ? 'Order marked as returned as damaged. Stock was not restored.'
            : 'Order marked as returned. Stock was restored.';

        return response()->json([
            'message' => $message,
            'data' => $data,
        ]);
    }
}
