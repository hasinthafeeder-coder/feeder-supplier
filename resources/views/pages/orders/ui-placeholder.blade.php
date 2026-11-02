@extends('layout_main.app')

@php
    $pageTitle = $pageTitle ?? 'Coming soon';
    $pageCopy = $pageCopy ?? 'This page will be available in a later Phase 2B step.';
    $crumbs = $crumbs ?? [['label' => $pageTitle]];
@endphp

@push('styles')
    @include('pages.orders.partials.ui-styles')
@endpush

@section('content')
    <div class="main-content-container overflow-hidden supplier-orders-ui">
        @include('pages.orders.partials.ui-page-header', [
            'pageTitle' => $pageTitle,
            'pageCopy' => $pageCopy,
            'crumbs' => $crumbs,
        ])

        <div class="card bg-white rounded-10 border border-white mb-4">
            <div class="p-20 text-center py-5">
                <span class="material-symbols-outlined d-block mb-2" style="font-size: 42px; color: #94a3b8;" aria-hidden="true">construction</span>
                <h4 class="fs-18 mb-2">Coming soon</h4>
                <p class="fs-14 text-body mb-0">{{ $pageCopy }}</p>
            </div>
        </div>
    </div>
@endsection
