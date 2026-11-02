<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\IndexFinancialOverviewRequest;
use App\Services\Finance\SupplierFinancialOverviewReadService;
use Feeder\Core\Models\SupplierSettlementBatch;
use Feeder\Core\Models\User;
use Feeder\Core\Services\PortalFinancialOverviewMarkets;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Supplier portal financial overview.
 *
 * Reads the authenticated supplier's own settlement lines. It does not generate
 * settlements or record payments.
 */
class FinancialOverviewController extends Controller
{
    public function __construct(
        private readonly SupplierFinancialOverviewReadService $overview,
        private readonly PortalFinancialOverviewMarkets $overviewMarkets,
    ) {}

    public function index(IndexFinancialOverviewRequest $request): View
    {
        $actor = $this->actor($request);
        $filters = $request->filters();
        $markets = $this->overviewMarkets->viewData($actor);
        $overview = $this->overview->forOverview(
            $actor,
            $markets['overviewMarkets'] instanceof Collection ? $markets['overviewMarkets'] : new Collection(),
            $filters['date_from'] !== '' ? $filters['date_from'] : null,
            $filters['date_to'] !== '' ? $filters['date_to'] : null,
            15,
        );

        return view('pages.finance.overview', [
            'filters' => $filters,
            'rows' => $overview['rows'],
            'marketSummaries' => $overview['summaries'],
            ...$markets,
        ]);
    }

    public function showBatch(Request $request, SupplierSettlementBatch $batch): View
    {
        $detail = $this->overview->loadOwnedBatchDetail($this->actor($request), $batch);

        return view('pages.finance.batch-show', $detail);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        return $user;
    }
}
