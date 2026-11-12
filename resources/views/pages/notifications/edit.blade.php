@extends('layout_main.app')

@php
    $publishValue = old(
        'publish_at',
        \Feeder\Core\Support\BusinessTimezone::formatForDateTimeLocal($notification->publish_at)
    );
    $expiresValue = old(
        'expires_at',
        \Feeder\Core\Support\BusinessTimezone::formatForDateTimeLocal($notification->expires_at)
    );
@endphp

@section('content')
    <div class="main-content-container overflow-hidden" id="supplierNotificationEdit">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <div>
                <h3 class="mb-1">Edit notification</h3>
                <p class="fs-15 text-body mb-0">Update title, message, schedule, and banner. Type, product, audience, and recipients stay fixed.</p>
            </div>
            <a href="{{ route('notifications.show', $notification) }}" class="btn btn-light border">Back to notification</a>
        </div>

        <div class="card bg-white border border-white rounded-10 p-20 mb-4">
            @if ($errors->any())
                <div class="alert alert-danger border mb-3" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="fs-12 text-uppercase text-secondary mb-1">Type</div>
                    <div class="fw-medium">{{ $notification->type->label() }}</div>
                </div>
                <div class="col-md-3">
                    <div class="fs-12 text-uppercase text-secondary mb-1">Product</div>
                    <div class="fw-medium">{{ $notification->product?->name ?: '—' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="fs-12 text-uppercase text-secondary mb-1">Audience</div>
                    <div class="fw-medium">{{ $notification->audience_type->label() }}</div>
                </div>
                <div class="col-md-3">
                    <div class="fs-12 text-uppercase text-secondary mb-1">Recipients</div>
                    <div class="fw-medium">{{ $notification->recipients->count() }}</div>
                </div>
            </div>

            <form method="POST" action="{{ route('notifications.update', $notification) }}" enctype="multipart/form-data" class="row g-3">
                @csrf
                @method('PUT')
                <div class="col-12">
                    <label for="notificationTitle" class="label fs-14 mb-2">Title</label>
                    <input type="text" class="form-control" id="notificationTitle" name="title" maxlength="200"
                        value="{{ old('title', $notification->title) }}" required>
                </div>
                <div class="col-12">
                    <label for="notificationBody" class="label fs-14 mb-2">Message</label>
                    <textarea class="form-control" id="notificationBody" name="body" rows="5" maxlength="10000" required>{{ old('body', $notification->body) }}</textarea>
                </div>
                <div class="col-md-6">
                    <label for="notificationPublishAt" class="label fs-14 mb-2">Publish at</label>
                    <input type="datetime-local" class="form-control" id="notificationPublishAt" name="publish_at"
                        value="{{ $publishValue }}" required>
                </div>
                <div class="col-md-6">
                    <label for="notificationExpiresAt" class="label fs-14 mb-2">Expires at</label>
                    <input type="datetime-local" class="form-control" id="notificationExpiresAt" name="expires_at"
                        value="{{ $expiresValue }}">
                </div>
                <div class="col-md-8">
                    <label for="notificationBanner" class="label fs-14 mb-2">Replace banner</label>
                    <input type="file" class="form-control" id="notificationBanner" name="banner"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="fs-13 text-body mt-1">
                        Current banner:
                        {{ $notification->banner_file_uuid ? ($notification->bannerFile?->original_name ?: $notification->banner_file_uuid) : 'none' }}
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="removeBanner" name="remove_banner"
                            @checked(old('remove_banner'))>
                        <label class="form-check-label" for="removeBanner">Remove current banner</label>
                    </div>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary text-white">Save changes</button>
                    <a href="{{ route('notifications.show', $notification) }}" class="btn btn-light border">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
