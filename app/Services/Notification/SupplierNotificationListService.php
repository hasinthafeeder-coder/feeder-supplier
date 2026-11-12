<?php

namespace App\Services\Notification;

use App\Http\Requests\Notification\IndexNotificationRequest;
use Feeder\Core\Models\Notification;
use Feeder\Core\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Supplier-owned outbound notifications. Never lists admin announcements or
 * another company's records.
 */
class SupplierNotificationListService
{
    public const PER_PAGE = 15;

    public function paginate(User $supplier, IndexNotificationRequest $request): LengthAwarePaginator
    {
        return $this->filtered($supplier, $this->filters($request))
            ->with(['product', 'bannerFile'])
            ->withCount('recipients')
            ->orderByDesc('publish_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @return array{search: string, type: string, schedule: string}
     */
    public function filters(IndexNotificationRequest $request): array
    {
        $validated = $request->validated();

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'type' => (string) ($validated['type'] ?? ''),
            'schedule' => (string) ($validated['schedule'] ?? ''),
        ];
    }

    /**
     * @param  array{search: string, type: string, schedule: string}  $filters
     * @return Builder<Notification>
     */
    private function filtered(User $supplier, array $filters): Builder
    {
        $query = Notification::query()
            ->forPublisherCompany((int) $supplier->company_id)
            ->forCreator((int) $supplier->id)
            ->whereIn('type', array_map(
                static fn ($type) => $type->value,
                \Feeder\Core\Enums\NotificationType::supplierManagedCases()
            ));

        $now = Carbon::now();

        if ($filters['search'] !== '') {
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('title', 'like', $term)
                    ->orWhere('body', 'like', $term)
                    ->orWhere('uuid', 'like', $term);
            });
        }

        if ($filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        if ($filters['schedule'] === 'scheduled') {
            $query->where('publish_at', '>', $now);
        } elseif ($filters['schedule'] === 'active') {
            $query->currentlyVisible($now);
        } elseif ($filters['schedule'] === 'expired') {
            $query->whereNotNull('expires_at')->where('expires_at', '<=', $now);
        }

        return $query;
    }
}
