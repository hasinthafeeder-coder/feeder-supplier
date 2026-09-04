<?php

namespace App\Http\Controllers\Grn;

use App\Http\Controllers\Controller;
use App\Http\Requests\Grn\StoreGoodsReceivedNoteRequest;
use App\Services\FileServerService;
use Feeder\Core\Models\GoodsReceivedNote;
use Feeder\Core\Models\Product;
use Feeder\Core\Services\GoodsReceivedNoteService;
use Feeder\Core\Support\CurrencyDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class GoodsReceivedNoteController extends Controller
{
    public function __construct(
        private readonly GoodsReceivedNoteService $grnService,
        private readonly FileServerService $fileServerService,
    ) {}

    public function index(Request $request): View
    {
        $supplierId = (int) Auth::id();
        $search = trim((string) $request->input('search', ''));

        $grns = GoodsReceivedNote::query()
            ->forSupplier($supplierId)
            ->withCount('items')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('grn_number', 'like', "%{$search}%")
                        ->orWhere('invoice_number', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('pages.grns.list', [
            'grns' => $grns,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(): View
    {
        return view('pages.grns.form', $this->formViewData());
    }

    public function store(StoreGoodsReceivedNoteRequest $request): RedirectResponse
    {
        $invoiceFileId = $request->hasFile('invoice')
            ? $this->fileServerService->uploadInvoice($request->file('invoice'))['id']
            : null;

        $grn = $this->grnService->createGrn(
            (int) Auth::id(),
            $request->headerData(),
            $request->normalizedItems(),
            $invoiceFileId,
        );

        return redirect()
            ->route('grns.show', $grn)
            ->with('success', 'GRN created successfully.');
    }

    public function show(GoodsReceivedNote $grn): View
    {
        $this->authorizeGrnOwner($grn);

        $grn->load([
            'items',
            'supplier.company',
            'invoiceFile',
            'creator',
        ]);

        return view('pages.grns.details', [
            'grn' => $grn,
            'currency' => $this->resolveSupplierCurrency(),
        ]);
    }

    public function edit(GoodsReceivedNote $grn): View
    {
        $this->authorizeGrnOwner($grn);

        $grn->load(['items', 'invoiceFile']);

        return view('pages.grns.form', $this->formViewData($grn));
    }

    public function update(StoreGoodsReceivedNoteRequest $request, GoodsReceivedNote $grn): RedirectResponse
    {
        $this->authorizeGrnOwner($grn);

        $replaceInvoiceFile = $request->hasFile('invoice');
        $invoiceFileId = $replaceInvoiceFile
            ? $this->fileServerService->uploadInvoice($request->file('invoice'))['id']
            : null;

        $this->grnService->updateGrn(
            $grn,
            $request->headerData(),
            $request->normalizedItems(),
            $invoiceFileId,
            $replaceInvoiceFile,
        );

        return redirect()
            ->route('grns.show', $grn)
            ->with('success', 'GRN updated successfully.');
    }

    public function destroy(GoodsReceivedNote $grn): RedirectResponse
    {
        $this->authorizeGrnOwner($grn);

        $this->grnService->deleteGrn($grn);

        return redirect()
            ->route('grns.index')
            ->with('success', 'GRN deleted successfully.');
    }

    private function authorizeGrnOwner(GoodsReceivedNote $grn): void
    {
        abort_if((int) $grn->supplier_id !== (int) Auth::id(), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function formViewData(?GoodsReceivedNote $grn = null): array
    {
        $supplierId = (int) Auth::id();

        $products = Product::query()
            ->forSupplier($supplierId)
            ->with(['variants' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('name')
            ->get();

        $productCatalog = $products->map(function (Product $product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'variants' => $product->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'cost' => (string) $variant->cost,
                ])->values(),
            ];
        })->values();

        $currency = $this->resolveSupplierCurrency();

        return [
            'grn' => $grn,
            'productCatalog' => $productCatalog,
            'currencyIso' => CurrencyDisplay::inputLabel($currency),
            'existingInvoiceUuid' => $grn?->invoiceFile?->uuid,
            'existingInvoiceName' => $grn?->invoiceFile?->original_name,
            'existingInvoiceUrl' => $grn?->invoiceFile?->uuid
                ? route('files.view', ['uuid' => $grn->invoiceFile->uuid])
                : null,
        ];
    }

    private function resolveSupplierCurrency(): ?\Feeder\Core\Models\Currency
    {
        /** @var \Feeder\Core\Models\User $user */
        $user = Auth::user();
        $user->loadMissing('company.operationMarket.currency');

        return $user->company?->operationMarket?->currency;
    }
}
