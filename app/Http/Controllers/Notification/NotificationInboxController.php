<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Feeder\Core\Models\Notification;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Notification\NotificationInboxService;
use Feeder\Core\Services\Notification\NotificationPublicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Supplier recipient inbox (admin announcements / finance notices).
 *
 * Distinct from NotificationController, which manages supplier-published
 * product notifications.
 */
class NotificationInboxController extends Controller
{
    public function __construct(
        private readonly NotificationInboxService $inbox,
        private readonly NotificationPublicationService $publisher,
    ) {}

    public function index(Request $request): View
    {
        $actor = $this->actor();
        $perPage = max(1, min(50, (int) $request->integer('per_page', 20)));

        return view('pages.notifications.inbox.index', [
            'recipients' => $this->inbox->paginateVisibleFor($actor, $perPage),
            'unreadCount' => $this->inbox->unreadCountFor($actor),
        ]);
    }

    public function show(Notification $notification): View
    {
        $actor = $this->actor();

        try {
            $recipient = $this->inbox->markOpenedByNotificationUuid($actor, (string) $notification->uuid);
        } catch (ValidationException) {
            abort(403);
        }

        $loaded = $this->publisher->loadForDisplay($recipient->notification ?? $notification);

        return view('pages.notifications.inbox.show', [
            'notification' => $loaded,
            'recipient' => $recipient,
        ]);
    }

    public function markRead(Notification $notification): RedirectResponse
    {
        $actor = $this->actor();

        try {
            $this->inbox->markReadByNotificationUuid($actor, (string) $notification->uuid);
        } catch (ValidationException) {
            abort(403);
        }

        return redirect()
            ->route('notifications.inbox.show', $notification)
            ->with('success', 'Notification marked as read.');
    }

    private function actor(): User
    {
        $actor = Auth::user();

        if (! $actor instanceof User) {
            abort(403);
        }

        return $actor;
    }
}
