@extends('layout_main.app')

@php
    use Feeder\Core\Enums\NotificationDeliveryChannel;
    use Illuminate\Support\Carbon;

    $now = Carbon::now();
    $status = $notification->publish_at?->gt($now)
        ? 'Scheduled'
        : ($notification->isPublishedAt($now) ? 'Active' : 'Expired');
@endphp

@section('content')
    <div class="main-content-container overflow-hidden" id="supplierNotificationShow">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <div>
                <h3 class="mb-1">{{ $notification->title }}</h3>
                <p class="fs-15 text-body mb-0">Supplier product notification. Type, product, audience, and recipients stay fixed after create.</p>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2">
                @can('notifications.create')
                    <a href="{{ route('notifications.edit', $notification) }}" class="btn btn-primary text-white">Edit</a>
                @endcan
                <a href="{{ route('notifications.index') }}" class="btn btn-light border">Back to notifications</a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success border mb-3" role="alert">{{ session('success') }}</div>
        @endif

        <div class="card bg-white border border-white rounded-10 mb-4">
            <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="fs-18 mb-0">Notification</h4>
                <span class="text-secondary bg-secondary bg-opacity-10 fs-14 fw-normal d-inline-block default-badge">
                    {{ $status }}
                </span>
            </div>
            <div class="p-20">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="fs-12 text-uppercase text-secondary mb-1">UUID</div>
                        <div class="fw-medium">{{ $notification->uuid }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-12 text-uppercase text-secondary mb-1">Type</div>
                        <div class="fw-medium">{{ $notification->type->label() }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-12 text-uppercase text-secondary mb-1">Product</div>
                        <div class="fw-medium">{{ $notification->product?->name ?: '—' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="fs-12 text-uppercase text-secondary mb-1">Message</div>
                        <div class="fw-medium" style="white-space: pre-wrap;">{{ $notification->body }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-12 text-uppercase text-secondary mb-1">Audience</div>
                        <div class="fw-medium">{{ $notification->audience_type->label() }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-12 text-uppercase text-secondary mb-1">Publish at</div>
                        <div class="fw-medium">{{ $notification->publish_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-12 text-uppercase text-secondary mb-1">Expires at</div>
                        <div class="fw-medium">{{ $notification->expires_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-12 text-uppercase text-secondary mb-1">Banner</div>
                        <div class="fw-medium">
                            {{ $notification->banner_file_uuid ? ($notification->bannerFile?->original_name ?: $notification->banner_file_uuid) : '—' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-white border border-white rounded-10 mb-4">
            <div class="p-20 border-bottom">
                <h4 class="fs-18 mb-0">Recipients</h4>
            </div>
            <div class="default-table-area mx-minus-1">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th scope="col" class="fw-medium">Company</th>
                                <th scope="col" class="fw-medium">User</th>
                                <th scope="col" class="fw-medium">Read</th>
                                <th scope="col" class="fw-medium">In-app delivery</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($notification->recipients as $recipient)
                                @php
                                    $delivery = $recipient->deliveries->first(
                                        fn ($row) => $row->channel === NotificationDeliveryChannel::IN_APP
                                    ) ?? $recipient->deliveries->first();
                                @endphp
                                <tr>
                                    <td class="text-body">{{ $recipient->recipientUser?->company?->name ?: '—' }}</td>
                                    <td class="text-body">{{ $recipient->recipientUser?->email ?: '—' }}</td>
                                    <td class="text-body">
                                        {{ $recipient->read_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: 'Unread' }}
                                    </td>
                                    <td class="text-body">{{ $delivery?->status?->value ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-5">No recipients.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
