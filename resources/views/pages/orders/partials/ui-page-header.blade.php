@php
    $pageTitle = $pageTitle ?? 'Orders';
    $pageCopy = $pageCopy ?? '';
    $crumbs = $crumbs ?? [];
@endphp
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2 mt-1">
    <div>
        <h3 class="mb-1">{{ $pageTitle }}</h3>
        @if ($pageCopy !== '')
            <p class="fs-15 text-body mb-0">{{ $pageCopy }}</p>
        @endif
    </div>
</div>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb align-items-center mb-0 lh-1">
        <li class="breadcrumb-item">
            <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
                <i class="ri-home-8-line fs-15 text-primary me-1"></i>
                <span class="text-body fs-14 hover">Dashboard</span>
            </a>
        </li>
        @foreach ($crumbs as $crumb)
            <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}" @if ($loop->last) aria-current="page" @endif>
                @if (! $loop->last && ! empty($crumb['href']))
                    <a href="{{ $crumb['href'] }}" class="text-decoration-none">
                        <span class="text-body fs-14 hover">{{ $crumb['label'] }}</span>
                    </a>
                @else
                    <span class="text-secondary">{{ $crumb['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
