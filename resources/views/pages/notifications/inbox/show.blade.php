@extends('layout_main.app')

@section('content')
    <div class="main-content-container overflow-hidden" id="supplierNotificationInboxShow">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <div>
                <h3 class="mb-1">{{ $notification->title }}</h3>
                <p class="fs-15 text-body mb-0">
                    {{ $notification->type->label() }}
                    ·
                    {{ $notification->publish_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                </p>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2">
                <a href="{{ route('notifications.inbox.index') }}" class="btn btn-light border">Back to inbox</a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success border mb-3" role="alert">{{ session('success') }}</div>
        @endif

        @if ($notification->banner_file_uuid && $notification->bannerFile)
            <div class="mb-4 rounded-10 overflow-hidden border" style="max-height: 280px;">
                <img src="{{ route('files.view', $notification->banner_file_uuid) }}"
                    alt="{{ $notification->title }}"
                    class="w-100"
                    style="max-height: 280px; object-fit: cover;">
            </div>
        @endif

        <div class="card bg-white border border-white rounded-10 mb-4">
            <div class="p-20 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="fs-18 mb-0">Notification</h4>
                @if ($recipient->read_at === null)
                    <span class="text-primary bg-primary bg-opacity-10 fs-14 fw-normal d-inline-block default-badge">
                        Unread
                    </span>
                @else
                    <span class="text-secondary bg-secondary bg-opacity-10 fs-14 fw-normal d-inline-block default-badge">
                        Read
                        {{ $recipient->read_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                    </span>
                @endif
            </div>
            <div class="p-20">
                <div class="row g-3">
                    @if ($notification->product)
                        <div class="col-md-4">
                            <div class="fs-12 text-uppercase text-secondary mb-1">Product</div>
                            <div class="fw-medium">{{ $notification->product->name }}</div>
                        </div>
                    @endif
                    <div class="col-12">
                        <div class="fs-12 text-uppercase text-secondary mb-1">Message</div>
                        <div class="fw-medium" style="white-space: pre-wrap;">{{ $notification->body }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
