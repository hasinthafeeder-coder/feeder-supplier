<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Notification\NotificationInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * JSON poll endpoint for open supplier tabs (bell, sidebar, inbox).
 */
class NotificationLiveController extends Controller
{
    public const POLL_INTERVAL_MS = 15000;

    public function __construct(
        private readonly NotificationInboxService $inbox,
    ) {}

    public function __invoke(): JsonResponse
    {
        $actor = Auth::user();
        if (! $actor instanceof User) {
            abort(403);
        }

        if (! $actor->hasPermission(NotificationInboxService::PERMISSION_VIEW)) {
            abort(403);
        }

        try {
            $snapshot = $this->inbox->liveSnapshotFor($actor, 5);
        } catch (ValidationException) {
            return response()->json([
                'unread_count' => 0,
                'unread_meeting_count' => 0,
                'latest_recipient_id' => 0,
                'version' => '0:0:0:',
                'items' => [],
                'banners' => [],
                'poll_interval_ms' => self::POLL_INTERVAL_MS,
            ]);
        }

        $items = $snapshot['recipients']->map(function ($recipient) {
            $notification = $recipient->notification;
            $bannerUuid = $notification?->banner_file_uuid;

            return [
                'recipient_uuid' => (string) $recipient->uuid,
                'notification_uuid' => (string) ($notification?->uuid ?? ''),
                'title' => (string) ($notification?->title ?? ''),
                'body_preview' => Str::limit((string) ($notification?->body ?? ''), 120),
                'type_label' => $notification?->type?->label() ?: 'Notice',
                'publish_at' => optional($notification?->publish_at)->toIso8601String(),
                'publish_at_label' => optional($notification?->publish_at)
                    ?->timezone(config('app.timezone'))
                    ->format('Y-m-d H:i'),
                'publish_at_human' => optional($notification?->publish_at)->diffForHumans(),
                'is_unread' => $recipient->read_at === null,
                'banner_url' => ($bannerUuid && $notification?->bannerFile)
                    ? route('files.view', $bannerUuid)
                    : null,
                'show_url' => $notification
                    ? route('notifications.inbox.show', $notification)
                    : null,
            ];
        })->values()->all();

        $version = implode(':', [
            (string) $snapshot['latest_recipient_id'],
            (string) $snapshot['unread_count'],
            (string) $snapshot['unread_meeting_count'],
            '',
        ]);

        return response()->json([
            'unread_count' => $snapshot['unread_count'],
            'unread_meeting_count' => $snapshot['unread_meeting_count'],
            'latest_recipient_id' => $snapshot['latest_recipient_id'],
            'version' => $version,
            'items' => $items,
            'banners' => [],
            'poll_interval_ms' => self::POLL_INTERVAL_MS,
        ]);
    }
}
