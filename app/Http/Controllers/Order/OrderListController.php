<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use Feeder\Core\Services\Order\SupplierOrderApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderListController extends Controller
{
    public function __construct(
        private readonly SupplierOrderApiService $orderApiService,
    ) {
    }

    public function fresh(Request $request): JsonResponse
    {
        return response()->json(
            $this->orderApiService->listFresh(
                (int) Auth::id(),
                $this->filters($request),
            )
        );
    }

    public function outOfStock(Request $request): JsonResponse
    {
        return response()->json(
            $this->orderApiService->listOutOfStock(
                (int) Auth::id(),
                $this->filters($request),
            )
        );
    }

    public function print(Request $request): JsonResponse
    {
        return response()->json(
            $this->orderApiService->listPrint(
                (int) Auth::id(),
                $this->filters($request),
            )
        );
    }

    public function packaging(Request $request): JsonResponse
    {
        return response()->json(
            $this->orderApiService->listPackaging(
                (int) Auth::id(),
                $this->filters($request),
            )
        );
    }

    /**
     * @return array{
     *     search: string,
     *     product_id: int|null,
     *     courier_id: int|null,
     *     date_from: string|null,
     *     date_to: string|null,
     *     page: int,
     *     per_page: int
     * }
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->input('search', '')),
            'product_id' => $request->filled('product_id') ? (int) $request->input('product_id') : null,
            'courier_id' => $request->filled('courier_id') ? (int) $request->input('courier_id') : null,
            'date_from' => $request->filled('date_from') ? (string) $request->input('date_from') : null,
            'date_to' => $request->filled('date_to') ? (string) $request->input('date_to') : null,
            'page' => max(1, (int) $request->input('page', 1)),
            'per_page' => max(1, min(100, (int) $request->input('per_page', 25))),
        ];
    }
}
