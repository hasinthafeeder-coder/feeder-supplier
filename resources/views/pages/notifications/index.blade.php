@extends('layout_main.app')

@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    $now = Carbon::now();
@endphp

@section('content')
    <div class="main-content-container overflow-hidden" id="supplierNotificationsPage">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <div>
                <h3 class="mb-1">Notifications</h3>
                <p class="fs-15 text-body mb-0">Product offers, restock notices, and product information updates for eligible resellers.</p>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2">
                @can('notifications.create')
                    <a href="{{ route('notifications.create') }}" class="btn btn-primary text-white">Create notification</a>
                @endcan
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb align-items-center mb-0 lh-1">
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
                                <i class="ri-home-8-line fs-15 text-primary me-1"></i>
                                <span class="text-body fs-14 hover">Dashboard</span>
                            </a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            <span class="text-secondary">Notifications</span>
                        </li>
                    </ol>
                </nav>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success border mb-3" role="alert">{{ session('success') }}</div>
        @endif

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20 border-bottom">
                <form method="GET" action="{{ route('notifications.index') }}" class="row g-3 align-items-end">
                    <div class="col-12 col-lg-4">
                        <label for="notificationSearch" class="label fs-14 mb-2">Search</label>
                        <input type="text" class="form-control" id="notificationSearch" name="search"
                            value="{{ $filters['search'] }}" placeholder="Title, message, or UUID">
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <label for="notificationType" class="label fs-14 mb-2">Type</label>
                        <select class="form-select form-control" id="notificationType" name="type">
                            <option value="">All types</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>
                                    {{ $type->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <label for="notificationSchedule" class="label fs-14 mb-2">Schedule</label>
                        <select class="form-select form-control" id="notificationSchedule" name="schedule">
                            <option value="">All</option>
                            <option value="scheduled" @selected($filters['schedule'] === 'scheduled')>Scheduled</option>
                            <option value="active" @selected($filters['schedule'] === 'active')>Active</option>
                            <option value="expired" @selected($filters['schedule'] === 'expired')>Expired</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary text-white">Apply</button>
                        <a href="{{ route('notifications.index') }}" class="btn btn-light border">Reset</a>
                    </div>
                </form>
            </div>

            <div class="default-table-area mx-minus-1">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th scope="col" class="fw-medium">Title</th>
                                <th scope="col" class="fw-medium">Type</th>
                                <th scope="col" class="fw-medium">Product</th>
                                <th scope="col" class="fw-medium">Publish</th>
                                <th scope="col" class="fw-medium">Recipients</th>
                                <th scope="col" class="fw-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($notifications as $notification)
                                @php
                                    $status = $notification->publish_at?->gt($now)
                                        ? 'Scheduled'
                                        : ($notification->isPublishedAt($now) ? 'Active' : 'Expired');
                                @endphp
                                <tr>
                                    <td class="text-body">
                                        <a href="{{ route('notifications.show', $notification) }}" class="fw-medium text-decoration-none">
                                            {{ $notification->title }}
                                        </a>
                                        <div class="fs-13 text-secondary">{{ Str::limit($notification->body, 80) }}</div>
                                    </td>
                                    <td class="text-body">{{ $notification->type->label() }}</td>
                                    <td class="text-body">{{ $notification->product?->name ?: '—' }}</td>
                                    <td class="text-body">{{ $notification->publish_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                    <td class="text-body">{{ $notification->recipients_count }}</td>
                                    <td class="text-body">
                                        <span class="text-secondary bg-secondary bg-opacity-10 fs-14 fw-normal d-inline-block default-badge">
                                            {{ $status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">No notifications published.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('partials.pagination', [
                    'paginator' => $notifications,
                    'ariaLabel' => 'Notifications pagination',
                ])
            </div>
        </div>
    </div>
@endsection
