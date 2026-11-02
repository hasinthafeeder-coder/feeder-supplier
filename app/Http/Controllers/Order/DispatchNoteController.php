<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreDispatchNoteRequest;
use Feeder\Core\Exceptions\SupplierFulfillmentException;
use Feeder\Core\Services\Order\SupplierDispatchNoteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Dispatch Note create / print endpoints for Supplier Dispatch Summary.
 *
 * Isolated from order status transitions, packing, remittance, and settlement.
 */
class DispatchNoteController extends Controller
{
    public function __construct(
        private readonly SupplierDispatchNoteService $dispatchNoteService,
    ) {
    }

    public function store(StoreDispatchNoteRequest $request): JsonResponse
    {
        $courierId = $request->filled('courier_id')
            ? (int) $request->input('courier_id')
            : null;

        try {
            $note = $this->dispatchNoteService->createForCourier(
                (int) Auth::id(),
                $courierId,
                (int) Auth::id(),
            );
        } catch (SupplierFulfillmentException $exception) {
            return $exception->toResponse();
        }

        return response()->json([
            'message' => 'Dispatch Note created. Orders remain DISPATCHED.',
            'data' => $note,
        ], 201);
    }

    public function print(string $uuid): View
    {
        try {
            $note = $this->dispatchNoteService->findOwnedOrFail((int) Auth::id(), $uuid);
        } catch (SupplierFulfillmentException $exception) {
            abort($exception->status, $exception->getMessage());
        }

        return view('pages.orders.dispatch-note-print', [
            'note' => $this->dispatchNoteService->serializeNote($note),
        ]);
    }
}
