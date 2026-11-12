@extends('layout_main.app')

@section('content')
    <div class="main-content-container overflow-hidden" id="supplierNotificationCreate">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <div>
                <h3 class="mb-1">Create notification</h3>
                <p class="fs-15 text-body mb-0">Notify active resellers assigned to your supplier account by the admin. Recipients are resolved automatically.</p>
            </div>
            <a href="{{ route('notifications.index') }}" class="btn btn-light border">Back to notifications</a>
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

            @if ($products->isEmpty())
                <p class="text-body mb-0">No sellable products are available. Publish and get a product approved before creating notifications.</p>
            @else
                <form method="POST" action="{{ route('notifications.store') }}" enctype="multipart/form-data" class="row g-3" id="supplierNotificationForm">
                    @csrf
                    <div class="col-md-4">
                        <label for="notificationType" class="label fs-14 mb-2">Type</label>
                        <select class="form-select form-control" id="notificationType" name="type" required>
                            <option value="">Select a type</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}" @selected(old('type') === $type->value)>
                                    {{ $type->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="notificationProduct" class="label fs-14 mb-2">Product</label>
                        <select class="form-select form-control" id="notificationProduct" name="product_id" required>
                            <option value="">Select a product</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="notificationBanner" class="label fs-14 mb-2">Banner image</label>
                        <input type="file" class="form-control" id="notificationBanner" name="banner"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <div class="fs-13 text-body mt-1">Optional. JPG, PNG, or WebP up to 5 MB.</div>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-light border mb-0" role="status">
                            Audience is fixed to resellers assigned to your supplier by the admin. Unassigned resellers are never notified.
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="notificationTitle" class="label fs-14 mb-2">Title</label>
                        <input type="text" class="form-control" id="notificationTitle" name="title" maxlength="200"
                            value="{{ old('title') }}" required>
                    </div>
                    <div class="col-12">
                        <label for="notificationBody" class="label fs-14 mb-2">Message</label>
                        <textarea class="form-control" id="notificationBody" name="body" rows="5" maxlength="10000" required>{{ old('body') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="notificationPublishAt" class="label fs-14 mb-2">Publish at</label>
                        <input type="datetime-local" class="form-control" id="notificationPublishAt" name="publish_at"
                            value="{{ old('publish_at') }}">
                        <div class="fs-13 text-body mt-1">Leave blank to publish immediately. Times use Asia/Colombo.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="notificationExpiresAt" class="label fs-14 mb-2">Expires at</label>
                        <input type="datetime-local" class="form-control" id="notificationExpiresAt" name="expires_at"
                            value="{{ old('expires_at') }}">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary text-white">Publish notification</button>
                        <a href="{{ route('notifications.index') }}" class="btn btn-light border">Cancel</a>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
