@extends('layout_main.app')

@php
    $product = $product ?? null;
    $productStatus = old(
        'status',
        $product?->status instanceof \Feeder\Core\Enums\ProductStatus
            ? $product->status->value
            : ($product?->status ?? 'DRAFT')
    );
    $existingImages = $product ? $product->images->loadMissing('file') : collect();
    $existingImagePreviews = $existingImages
        ->map(function ($image) {
            $uuid = $image->file_uuid;

            if (! $uuid) {
                return null;
            }

            return [
                'id' => $image->id,
                'uuid' => $uuid,
                'url' => route('files.thumbnail', ['uuid' => $uuid, 'size' => 'md']),
            ];
        })
        ->filter()
        ->values();
    $remainingImageSlots = max(0, 4 - $existingImages->count());
    $existingGuidelineUuid = $product?->guidelineFile?->uuid ?? null;
    $existingGuidelineUrl = $existingGuidelineUuid
        ? route('files.view', ['uuid' => $existingGuidelineUuid])
        : null;

    $selectedCategoryId = old('category_id', $product?->category_id ?? '');

    $categories = collect($categories ?? [])
        ->sortBy([['parent_id', 'asc'], ['sort_order', 'asc'], ['name', 'asc']])
        ->values();

    $categoryTree = $categories
        ->map(function ($category) {
            $category->setRelation('children', collect());

            return $category;
        })
        ->keyBy('id');

    foreach ($categories as $category) {
        if (!empty($category->parent_id) && $categoryTree->has($category->parent_id)) {
            $parent = $categoryTree->get($category->parent_id);
            $children = $parent->getRelation('children') ?? collect();

            $children->push($category);

            $parent->setRelation('children', $children);
        }
    }

    $rootCategories = $categories
        ->filter(fn($category) => empty($category->parent_id))
        ->map(fn($category) => $categoryTree->get($category->id))
        ->filter()
        ->values();

    $statusBadgeClass = match ($productStatus) {
        'ACTIVE' => 'bg-success-subtle text-success border border-success border-opacity-10',
        'INACTIVE' => 'bg-danger-subtle text-danger border border-danger border-opacity-10',
        default => 'bg-primary-subtle text-primary border border-primary border-opacity-10',
    };
@endphp

@section('content')
    <div class="main-content-container overflow-hidden">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <h3 class="mb-0">{{ isset($product) ? 'Edit Product' : 'Create Product' }}</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb align-items-center mb-0 lh-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"
                            class="d-flex align-items-center text-decoration-none"><i
                                class="ri-home-8-line fs-15 text-primary me-1"></i><span
                                class="text-body fs-14 hover">Dashboard</span></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><span>Products</span></li>
                    <li class="breadcrumb-item active" aria-current="page"><span
                            class="text-secondary">{{ isset($product) ? 'Edit' : 'Create' }} Product</span></li>
                </ol>
            </nav>
        </div>

        @if (isset($errors) && $errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card bg-white p-20 rounded-10 border border-white mb-4">
            <form action="{{ isset($product) ? route('products.update', $product) : route('products.store') }}"
                method="POST" enctype="multipart/form-data">
                @csrf
                @if (isset($product))
                    @method('PUT')
                @endif
                <div class="row g-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-20">
                            <h3 class="mb-0">Product Information</h3>
                            <span class="badge {{ $statusBadgeClass }}">{{ $productStatus }}</span>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <div class="mb-0">
                                    <label class="label fs-16 mb-2">Product Name / Title</label>
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="productTitle" name="name"
                                            value="{{ old('name', $product?->name ?? '') }}"
                                            placeholder="Product Name / Title" required>
                                        <label for="productTitle">Product Name / Title</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="mb-0">
                                    <label class="label fs-16 mb-2">Product Category</label>
                                    <div class="form-floating">
                                        <select class="form-select form-control" id="productCategory" name="category_id"
                                            required>
                                            <option value="">Select category</option>
                                            @if ($rootCategories->isNotEmpty())
                                                @foreach ($rootCategories as $category)
                                                    @include('pages.products.partials.category-options', [
                                                        'node' => $category,
                                                        'prefix' => '',
                                                        'isLast' => $loop->last,
                                                        'selectedCategoryId' => $selectedCategoryId,
                                                    ])
                                                @endforeach
                                            @endif
                                        </select>
                                        <label for="productCategory">Select category</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-group mb-0">
                                    <label class="label fs-16 mb-2">Description</label>
                                    <ul class="nav nav-tabs nav-tabs-separator" id="descriptionTabs" role="tablist">
                                        <li class="nav-item"><button class="nav-link active" id="lang-en-tab"
                                                data-bs-toggle="tab" data-bs-target="#lang-en"
                                                type="button">English</button></li>
                                        <li class="nav-item"><button class="nav-link" id="lang-si-tab" data-bs-toggle="tab"
                                                data-bs-target="#lang-si" type="button">Sinhala</button></li>
                                        <li class="nav-item"><button class="nav-link" id="lang-ta-tab" data-bs-toggle="tab"
                                                data-bs-target="#lang-ta" type="button">Tamil</button></li>
                                    </ul>
                                    <div class="tab-content border border-top-0 rounded-bottom-10 p-3 pt-0 bg-white">
                                        <div class="tab-pane fade show active" id="lang-en">
                                            <textarea class="form-control" rows="7" name="descriptions[en]" placeholder="English description">{{ old('descriptions.en', $product?->descriptions->firstWhere('language_code', 'en')?->description ?? '') }}</textarea>
                                        </div>
                                        <div class="tab-pane fade" id="lang-si">
                                            <textarea class="form-control" rows="7" name="descriptions[si]" placeholder="Sinhala description">{{ old('descriptions.si', $product?->descriptions->firstWhere('language_code', 'si')?->description ?? '') }}</textarea>
                                        </div>
                                        <div class="tab-pane fade" id="lang-ta">
                                            <textarea class="form-control" rows="7" name="descriptions[ta]" placeholder="Tamil description">{{ old('descriptions.ta', $product?->descriptions->firstWhere('language_code', 'ta')?->description ?? '') }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-0">
                                    <label class="label fs-16 mb-2">Product Images</label>
                                    <input type="file" class="form-control" id="productImages" name="images[]"
                                        accept="image/*" multiple data-max-files="{{ $remainingImageSlots }}">
                                    <small class="text-muted">Up to 4 images · PNG, JPG, WEBP</small>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-0">
                                    <label class="label fs-16 mb-2">Product Guideline (PDF)</label>
                                    <input type="file" class="form-control" id="productGuideline"
                                        name="guideline" accept="application/pdf">
                                    <small class="text-muted">Guideline / spec sheet</small>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="row g-3">
                                    <div class="col-lg-6">
                                        <div class="border rounded-10 p-3 bg-light-subtle h-100">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h4 class="mb-0">Image Preview</h4>
                                            </div>
                                            <div id="imagePreviewList" class="d-flex flex-wrap gap-3 align-items-start">
                                                @forelse ($existingImagePreviews as $imagePreview)
                                                    <div class="preview-image-box existing-image-preview"
                                                        data-image-id="{{ $imagePreview['id'] }}">
                                                        <img src="{{ $imagePreview['url'] }}"
                                                            alt="Uploaded product image thumbnail">
                                                    </div>
                                                @empty
                                                    <span class="text-muted" id="imagePreviewEmpty">No images uploaded yet</span>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="border rounded-10 p-3 bg-light-subtle h-100">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h4 class="mb-0">PDF Preview</h4>
                                            </div>
                                            <div id="pdfPreviewContainer" class="pdf-preview-box">
                                                @if ($existingGuidelineUrl)
                                                    <iframe
                                                        src="{{ $existingGuidelineUrl }}"
                                                        title="Product guideline preview"></iframe>
                                                @else
                                                    <span class="text-muted">No PDF selected</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="border rounded-10 p-3 bg-light-subtle">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <h4 class="mb-1">Product Variants</h4>
                                            <p class="mb-0 text-muted fs-13">Add variants with individual pricing.</p>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-primary" id="addVariantBtn"><i
                                                class="ri-add-line"></i> Add Variant</button>
                                    </div>
                                    <div id="variantList" class="d-flex flex-column gap-3"></div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="border rounded-10 p-3 bg-light-subtle mt-2">
                                    <h4 class="mb-20">Visibility & Pricing</h4>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="systemVisible"
                                                    name="system_visible" value="1"
                                                    {{ old('system_visible', $product?->system_visible ?? true) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="systemVisible">System Visible</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="webVisible"
                                                    name="web_visible" value="1"
                                                    {{ old('web_visible', $product?->web_visible ?? true) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="webVisible">Web Visible</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="priceLock"
                                                    name="price_locked" value="1"
                                                    {{ old('price_locked', $product?->price_locked ?? false) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="priceLock">Price Lock</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                        <button type="submit" name="save_action" value="draft" class="btn btn-light border">Save
                            Draft</button>
                        <button type="submit" name="save_action" value="publish"
                            class="btn btn-primary text-white">Publish Product</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <style>
        .nav-tabs-separator .nav-link {
            border: 1px solid #e5e7eb;
            border-bottom: 0;
            margin-right: 8px;
            border-radius: 8px 8px 0 0;
            color: #6b7280;
            background: #f8fafc;
        }

        .nav-tabs-separator .nav-link.active {
            background: #fff;
            color: #111827;
            border-color: #e5e7eb;
            font-weight: 600;
        }

        .variant-card {
            border: 1px solid #e5e7eb;
            background: #ffffff;
            border-radius: 10px;
            overflow: hidden;
        }

        .variant-header {
            padding: 15px;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }

        .variant-body {
            padding: 15px;
        }

        .upload-square {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            width: 100%;
            min-height: 200px;
        }

        .upload-card {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            width: min(100%, 220px);
            aspect-ratio: 1;
            border: 2px dashed #dfe4ea;
            border-radius: 20px;
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            color: #1f2937;
            text-align: left;
            padding: 18px 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.15);
        }

        .upload-card:hover {
            border-color: #7c3aed;
            background: linear-gradient(145deg, #faf5ff 0%, #f8fafc 100%);
            transform: translateY(-1px);
        }

        .upload-input {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .upload-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            border-radius: 18px;
            background: rgba(124, 58, 237, 0.09);
            color: #7c3aed;
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .upload-title {
            font-size: 1rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 4px;
        }

        .upload-hint {
            display: block;
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .upload-filename {
            display: inline-block;
            max-width: 100%;
            font-size: 0.72rem;
            color: #4b5563;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            background: rgba(15, 23, 42, 0.03);
            border-radius: 999px;
            padding: 4px 9px;
        }

        .preview-image-box {
            width: 110px;
            height: 110px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .preview-image-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .pdf-preview-box {
            min-height: 140px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            overflow: hidden;
        }

        .pdf-preview-box iframe {
            width: 100%;
            height: 220px;
            border: 0;
            border-radius: 8px;
            background: #fff;
        }
    </style>

    <script>
        (function() {
            let variantCount = 0;
            const variantList = document.getElementById('variantList');
            const addVariantBtn = document.getElementById('addVariantBtn');
            const existingVariants = @json($product?->variants ?? []);

            const existingImagePreviews = @json($existingImagePreviews);
            const maxProductImages = 4;

            const imagePreviewList = document.getElementById('imagePreviewList');
            const pdfPreviewContainer = document.getElementById('pdfPreviewContainer');
            const productImagesInput = document.getElementById('productImages');
            const productGuidelineInput = document.getElementById('productGuideline');

            function createExistingPreview(image) {
                const preview = document.createElement('div');
                preview.className = 'preview-image-box existing-image-preview';
                preview.dataset.imageId = String(image.id);

                const img = document.createElement('img');
                img.src = image.url;
                img.alt = 'Uploaded product image thumbnail';
                preview.appendChild(img);

                return preview;
            }

            function createNewPreview(file) {
                const preview = document.createElement('div');
                preview.className = 'preview-image-box new-image-preview';

                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.alt = file.name;
                preview.appendChild(img);

                return preview;
            }

            function updateImagePreview(files) {
                if (!imagePreviewList) {
                    return;
                }

                imagePreviewList.innerHTML = '';

                existingImagePreviews.forEach(function(image) {
                    imagePreviewList.appendChild(createExistingPreview(image));
                });

                const remainingSlots = Math.max(0, maxProductImages - existingImagePreviews.length);
                const selectedFiles = Array.from(files || [])
                    .filter(function(file) {
                        return file.type.startsWith('image/');
                    })
                    .slice(0, remainingSlots);

                selectedFiles.forEach(function(file) {
                    imagePreviewList.appendChild(createNewPreview(file));
                });

                if (existingImagePreviews.length === 0 && selectedFiles.length === 0) {
                    const empty = document.createElement('span');
                    empty.className = 'text-muted';
                    empty.id = 'imagePreviewEmpty';
                    empty.textContent = 'No images uploaded yet';
                    imagePreviewList.appendChild(empty);
                }
            }

            function enforceImageUploadLimit(input) {
                if (!input || !input.files) {
                    return;
                }

                const remainingSlots = Math.max(0, maxProductImages - existingImagePreviews.length);
                if (remainingSlots <= 0) {
                    input.value = '';
                    alert('You can upload up to 4 images for each product.');
                    updateImagePreview([]);
                    return;
                }

                if (input.files.length <= remainingSlots) {
                    return;
                }

                const dataTransfer = new DataTransfer();
                Array.from(input.files)
                    .slice(0, remainingSlots)
                    .forEach(function(file) {
                        dataTransfer.items.add(file);
                    });
                input.files = dataTransfer.files;
                alert('You can upload up to ' + remainingSlots + ' more image(s) for this product.');
            }

            function updatePdfPreview(file) {
                if (!pdfPreviewContainer) {
                    return;
                }

                if (!file) {
                    pdfPreviewContainer.innerHTML = '<span class="text-muted">No PDF selected</span>';
                    return;
                }

                const url = URL.createObjectURL(file);
                pdfPreviewContainer.innerHTML = '<iframe src="' + url + '" title="Product guideline preview"></iframe>';
            }

            document.querySelectorAll('.upload-input').forEach(function(input) {
                const filenameDisplay = input.closest('.upload-card')?.querySelector('.upload-filename');

                if (!filenameDisplay) {
                    return;
                }

                const updateFilename = function() {
                    const files = input.files && input.files.length > 0 ? Array.from(input.files).map(
                        function(file) {
                            return file.name;
                        }) : ['No file selected'];

                    if (input.multiple) {
                        filenameDisplay.textContent = files.length > 1 ? files.length + ' files selected' :
                            files[0];
                    } else {
                        filenameDisplay.textContent = files[0];
                    }
                };

                const enforceFileLimit = function() {
                    if (!input.multiple) {
                        updateFilename();
                        return;
                    }

                    const maxFiles = Number(input.dataset.maxFiles || 0);
                    if (maxFiles <= 0 || !input.files || input.files.length <= maxFiles) {
                        updateFilename();
                        return;
                    }

                    const selectedFiles = Array.from(input.files);
                    const dataTransfer = new DataTransfer();
                    selectedFiles.slice(0, maxFiles).forEach(function(file) {
                        dataTransfer.items.add(file);
                    });
                    input.files = dataTransfer.files;
                    alert('You can upload up to ' + maxFiles + ' images for this product.');
                    updateFilename();
                };

                input.addEventListener('change', function() {
                    enforceFileLimit();

                    if (input === productImagesInput) {
                        updateImagePreview(input.files);
                    }

                    if (input === productGuidelineInput) {
                        updatePdfPreview(input.files && input.files[0] ? input.files[0] : null);
                    }
                });
                updateFilename();
            });

            if (productImagesInput) {
                productImagesInput.addEventListener('change', function() {
                    enforceImageUploadLimit(productImagesInput);
                    updateImagePreview(productImagesInput.files);
                });

                updateImagePreview(productImagesInput.files);
            }

            if (productGuidelineInput) {
                updatePdfPreview(productGuidelineInput.files && productGuidelineInput.files[0] ? productGuidelineInput
                    .files[0] : null);
            }

            const priceLockInput = document.getElementById('priceLock');

            function isPriceLocked() {
                return !!(priceLockInput && priceLockInput.checked);
            }

            function syncSuggestedPrices() {
                if (!variantList) {
                    return;
                }

                const locked = isPriceLocked();

                variantList.querySelectorAll('.variant-card').forEach(function(card) {
                    const sellingInput = card.querySelector('.variant-selling-price');
                    const suggestedInput = card.querySelector('.variant-suggested-price');

                    if (!sellingInput || !suggestedInput) {
                        return;
                    }

                    if (locked) {
                        suggestedInput.value = sellingInput.value;
                        suggestedInput.readOnly = true;
                        suggestedInput.classList.add('bg-light');
                        suggestedInput.title = 'Suggested price is locked to selling price';
                    } else {
                        suggestedInput.readOnly = false;
                        suggestedInput.classList.remove('bg-light');
                        suggestedInput.title = '';
                    }
                });
            }

            function createVariant(variant = null) {
                variantCount += 1;
                const variantIndex = variantCount - 1;
                const variantId = 'variant-' + variantCount;

                const row = document.createElement('div');
                row.className = 'variant-card';
                row.id = variantId;
                const variantName = variant?.name ?? '';
                const variantBarcode = variant?.barcode ?? '';
                const variantCost = variant?.cost ?? '';
                const variantSelling = variant?.selling_price ?? '';
                const variantWeight = variant?.weight ?? '';
                const variantSuggested = isPriceLocked()
                    ? (variantSelling || variant?.suggested_price || '')
                    : (variant?.suggested_price ?? '');
                const variantCommission = variant?.company_commission ?? 150;
                row.innerHTML = `
                    <div class="variant-header d-flex justify-content-between align-items-center">
                        <div><h5 class="mb-0">Variant ${variantCount}</h5></div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary toggle-variant"><i class="ri-arrow-up-s-line"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-variant"><i class="ri-delete-bin-line"></i></button>
                        </div>
                    </div>
                    <div class="variant-body">
                        <div class="row g-3">
                            <div class="col-lg-8">
                                <label class="label fs-14 mb-2">Variant Name</label>
                                <input type="text" class="form-control" name="variants[${variantIndex}][name]" value="${variantName}" placeholder="Example: Red / Large" required>
                            </div>
                            <div class="col-lg-4">
                                <label class="label fs-14 mb-2">Barcode</label>
                                <input type="text" class="form-control" name="variants[${variantIndex}][barcode]" value="${variantBarcode}" placeholder="Barcode">
                            </div>
                            <div class="col-lg-6">
                                <label class="label fs-14 mb-2">Cost</label>
                                <div class="input-group">
                                    <span class="input-group-text">LKR</span>
                                    <input type="number" min="0" step="0.01" class="form-control" name="variants[${variantIndex}][cost]" value="${variantCost}" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <label class="label fs-14 mb-2">Selling Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">LKR</span>
                                    <input type="number" min="0" step="0.01" class="form-control variant-selling-price" name="variants[${variantIndex}][selling_price]" value="${variantSelling}" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <label class="label fs-14 mb-2">Weight (kg)</label>
                                <input type="number" min="0.001" step="0.001" class="form-control" name="variants[${variantIndex}][weight]" value="${variantWeight}" placeholder="0.100" required>
                            </div>
                            <div class="col-lg-6">
                                <label class="label fs-14 mb-2">Suggested Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">LKR</span>
                                    <input type="number" min="0" step="0.01" class="form-control variant-suggested-price" name="variants[${variantIndex}][suggested_price]" value="${variantSuggested}" placeholder="0.00">
                                </div>
                            </div>
                            <input type="hidden" name="variants[${variantIndex}][company_commission]" value="${variantCommission}">
                        </div>
                    </div>
                `;

                if (variant && variant.id) {
                    row.insertAdjacentHTML('beforeend',
                        `<input type="hidden" name="variants[${variantIndex}][id]" value="${variant.id}">`);
                }

                variantList.appendChild(row);
                syncSuggestedPrices();
            }

            if (priceLockInput) {
                priceLockInput.addEventListener('change', syncSuggestedPrices);
            }

            if (variantList) {
                variantList.addEventListener('input', function(event) {
                    if (!isPriceLocked()) {
                        return;
                    }

                    const sellingInput = event.target.closest('.variant-selling-price');
                    if (!sellingInput) {
                        return;
                    }

                    const card = sellingInput.closest('.variant-card');
                    const suggestedInput = card?.querySelector('.variant-suggested-price');
                    if (suggestedInput) {
                        suggestedInput.value = sellingInput.value;
                    }
                });
            }

            addVariantBtn.addEventListener('click', createVariant);
            variantList.addEventListener('click', function(event) {
                const removeButton = event.target.closest('.remove-variant');
                const toggleButton = event.target.closest('.toggle-variant');

                if (removeButton) {
                    const variant = removeButton.closest('.variant-card');
                    const totalVariants = variantList.querySelectorAll('.variant-card').length;
                    if (totalVariants > 1) {
                        variant.remove();
                    }
                }

                if (toggleButton) {
                    const variant = toggleButton.closest('.variant-card');
                    const body = variant.querySelector('.variant-body');
                    body.classList.toggle('d-none');
                }
            });

            if (existingVariants.length > 0) {
                existingVariants.forEach((variant) => {
                    createVariant(variant);
                });
            } else {
                createVariant();
            }

            syncSuggestedPrices();
        })();
    </script>
@endsection
