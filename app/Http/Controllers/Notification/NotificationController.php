<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\IndexNotificationRequest;
use App\Http\Requests\Notification\StoreNotificationRequest;
use App\Http\Requests\Notification\UpdateNotificationRequest;
use App\Services\Notification\SupplierNotificationListService;
use Feeder\Core\Enums\NotificationAudienceType;
use Feeder\Core\Enums\NotificationType;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Models\Notification;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Notification\NotificationPublicationService;
use Feeder\Core\Services\Notification\PublishNotificationData;
use Feeder\Core\Services\Notification\UpdateNotificationData;
use Feeder\Core\Services\ProductEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationPublicationService $publisher,
        private readonly SupplierNotificationListService $notifications,
        private readonly ProductEligibilityService $productEligibility,
    ) {}

    public function index(IndexNotificationRequest $request): View
    {
        $supplier = $this->actor();

        return view('pages.notifications.index', [
            'notifications' => $this->notifications->paginate($supplier, $request),
            'filters' => $this->notifications->filters($request),
            'types' => NotificationType::supplierManagedCases(),
        ]);
    }

    public function create(): View
    {
        $supplier = $this->actor();
        $products = $this->ownedSellableProducts($supplier);

        return view('pages.notifications.create', [
            'types' => NotificationType::supplierManagedCases(),
            'products' => $products,
        ]);
    }

    public function store(StoreNotificationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $supplier = $this->actor();
        $product = Product::query()->findOrFail((int) $validated['product_id']);

        $notification = $this->publisher->publishForSupplierAssignedResellers(
            $supplier,
            new PublishNotificationData(
                type: NotificationType::from($validated['type']),
                title: $validated['title'],
                body: $validated['body'],
                audienceType: NotificationAudienceType::ALL_ASSIGNED_RESELLERS_FOR_SUPPLIER,
                publishAt: $validated['publish_at'] ?? null,
                expiresAt: $validated['expires_at'] ?? null,
            ),
            $request->file('banner'),
            $product,
        );

        return redirect()
            ->route('notifications.show', $notification)
            ->with('success', 'Notification published.');
    }

    public function show(Notification $notification): View
    {
        $this->authorizeSupplierNotification($notification);

        return view('pages.notifications.show', [
            'notification' => $this->publisher->loadForDisplay($notification),
        ]);
    }

    public function edit(Notification $notification): View
    {
        $this->authorizeSupplierNotification($notification);

        return view('pages.notifications.edit', [
            'notification' => $this->publisher->loadForDisplay($notification),
        ]);
    }

    public function update(UpdateNotificationRequest $request, Notification $notification): RedirectResponse
    {
        $validated = $request->validated();
        $supplier = $this->authorizeSupplierNotification($notification);

        $updated = $this->publisher->updateContent(
            $notification,
            $supplier,
            new UpdateNotificationData(
                title: $validated['title'],
                body: $validated['body'],
                publishAt: $validated['publish_at'],
                expiresAt: $validated['expires_at'] ?? null,
                removeBanner: (bool) ($validated['remove_banner'] ?? false),
            ),
            $request->file('banner'),
        );

        return redirect()
            ->route('notifications.show', $updated)
            ->with('success', 'Notification updated.');
    }

    private function actor(): User
    {
        $actor = Auth::user();

        if (! $actor instanceof User) {
            abort(403);
        }

        return $actor;
    }

    private function authorizeSupplierNotification(Notification $notification): User
    {
        $supplier = $this->actor();

        try {
            $this->publisher->assertSupplierCanManage($notification, $supplier);
        } catch (ValidationException) {
            abort(403);
        }

        return $supplier;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function ownedSellableProducts(User $supplier)
    {
        return Product::query()
            ->forSupplier((int) $supplier->id)
            ->where('status', ProductStatus::ACTIVE)
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $product): bool => $this->productEligibility->isProductSellable($product))
            ->values();
    }
}
