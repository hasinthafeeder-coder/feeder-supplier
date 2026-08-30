@php
    $productLanguages = $productLanguages ?? [];
    $product = $product ?? null;
@endphp

<div class="form-group mb-0">
    <label class="label fs-16 mb-2">Description</label>
    <ul class="nav nav-tabs nav-tabs-separator" id="descriptionTabs" role="tablist">
        @foreach ($productLanguages as $index => $language)
            <li class="nav-item">
                <button
                    class="nav-link {{ $index === 0 ? 'active' : '' }}"
                    id="lang-{{ $language['code'] }}-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#lang-{{ $language['code'] }}"
                    type="button"
                >
                    {{ $language['tab_label'] }}
                </button>
            </li>
        @endforeach
    </ul>
    <div class="tab-content border border-top-0 rounded-bottom-10 p-3 pt-0 bg-white">
        @foreach ($productLanguages as $index => $language)
            <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}" id="lang-{{ $language['code'] }}">
                <textarea
                    class="form-control"
                    rows="7"
                    name="descriptions[{{ $language['code'] }}]"
                    placeholder="{{ $language['placeholder'] }}"
                >{{ old('descriptions.' . $language['code'], $product?->descriptions->firstWhere('language_code', $language['code'])?->description ?? '') }}</textarea>
            </div>
        @endforeach
    </div>
</div>
