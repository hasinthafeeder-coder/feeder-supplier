<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use Feeder\Core\Services\StockListService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(
        private readonly StockListService $stockListService,
    ) {}

    public function index(Request $request): View
    {
        $supplierId = (int) Auth::id();
        $search = trim((string) $request->input('search', ''));
        $stockStatus = (string) $request->input('stock_status', '');

        return view('pages.stock.list', [
            'variants' => $this->stockListService->paginateForSupplier($supplierId, $search, $stockStatus),
            'counts' => $this->stockListService->supplierCounts($supplierId),
            'filters' => [
                'search' => $search,
                'stock_status' => in_array($stockStatus, ['in_stock', 'out_of_stock'], true) ? $stockStatus : '',
            ],
        ]);
    }
}
