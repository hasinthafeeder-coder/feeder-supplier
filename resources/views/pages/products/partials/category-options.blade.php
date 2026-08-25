@php
    $selectedCategoryId = $selectedCategoryId ?? '';
    $label = $prefix . ($prefix === '' ? '' : ($isLast ? '└─ ' : '├─ ')) . $node->name;
@endphp

<option value="{{ $node->id }}" {{ (string) $selectedCategoryId === (string) $node->id ? 'selected' : '' }}>
    {{ $label }}
</option>

@if ($node->children->isNotEmpty())
    @foreach ($node->children as $child)
        @include('pages.products.partials.category-options', [
            'node' => $child,
            'prefix' => $prefix . ($isLast ? '    ' : '│   '),
            'isLast' => $loop->last,
            'selectedCategoryId' => $selectedCategoryId,
        ])
    @endforeach
@endif
