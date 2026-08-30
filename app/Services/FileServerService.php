<?php

namespace App\Services;

use Feeder\Core\Enums\ApplicationType;
use Feeder\Core\Enums\FileCategory;
use Feeder\Core\Models\File;
use Feeder\Core\Services\FileService;
use Feeder\Core\Services\UuidService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Throwable;

class FileServerService
{
    public function __construct(
        private readonly FileService $fileService,
    ) {}

    /**
     * @return array{id: int, uuid: string}
     */
    public function uploadProductImage(UploadedFile $file): array
    {
        return $this->uploadAndResolve(
            $file,
            'PRODUCT',
            FileCategory::PRODUCT_IMAGE->value,
            'images',
        );
    }

    /**
     * @return array{id: int, uuid: string}
     */
    public function uploadGuideline(UploadedFile $file): array
    {
        return $this->uploadAndResolve(
            $file,
            'PRODUCT',
            FileCategory::PRODUCT_GUIDELINE->value,
            'guideline',
        );
    }

    /**
     * @param  array<int, UploadedFile|null>  $files
     * @return array<int, array{id: int, uuid: string}>
     */
    public function uploadProductImages(array $files): array
    {
        $uploaded = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $uploaded[] = $this->uploadProductImage($file);
        }

        return $uploaded;
    }

    public function buildViewUrl(string $fileUuid): string
    {
        return route('files.view', ['uuid' => $fileUuid]);
    }

    public function buildThumbnailUrl(string $fileUuid, string $size = 'md'): string
    {
        return route('files.thumbnail', [
            'uuid' => $fileUuid,
            'size' => $size,
        ]);
    }

    /**
     * @return array{id: int, uuid: string}
     */
    private function uploadAndResolve(
        UploadedFile $file,
        string $entityType,
        string $category,
        string $errorField = 'images',
    ): array {
        try {
            $response = $this->fileService->upload(
                $file,
                ApplicationType::SUPPLIER->value,
                $entityType,
                UuidService::generate(),
                $category,
                auth()->user()?->uuid,
            );
        } catch (RequestException | ConnectionException | Throwable) {
            throw ValidationException::withMessages([
                $errorField => ['Unable to upload file. Please try again.'],
            ]);
        }

        $uuid = data_get($response, 'file.uuid');

        if (! is_string($uuid) || $uuid === '') {
            throw ValidationException::withMessages([
                $errorField => ['Unable to upload file. Please try again.'],
            ]);
        }

        $fileRecord = File::query()->where('uuid', $uuid)->first();

        if (! $fileRecord) {
            throw ValidationException::withMessages([
                $errorField => ['Uploaded file could not be linked. Please try again.'],
            ]);
        }

        return [
            'id' => (int) $fileRecord->id,
            'uuid' => $uuid,
        ];
    }
}
