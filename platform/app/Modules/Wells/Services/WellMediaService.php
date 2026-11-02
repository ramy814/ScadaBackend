<?php

declare(strict_types=1);

namespace App\Modules\Wells\Services;

use App\Modules\Wells\Models\WellMedia;
use App\Modules\Wells\Repositories\WellMediaRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WellMediaService
{
    public function __construct(private readonly WellMediaRepository $repository) {}

    public function storeMany(int $wellId, array $uploads): void
    {
        foreach ($uploads as $upload) {
            $this->store($wellId, $upload);
        }
    }

    public function response(int $id): StreamedResponse
    {
        $media = $this->repository->findOrFail($id);
        $path = (string) $media->getAttribute('file_path');

        abort_unless(Storage::disk('legacy_media')->exists($path), 404);

        return Storage::disk('legacy_media')->response(
            $path,
            (string) $media->getAttribute('original_name'),
            ['Content-Type' => (string) $media->getAttribute('mime_type')],
        );
    }

    public function delete(int $wellId, int $id): void
    {
        $media = $this->repository->findForWellOrFail($wellId, $id);
        $path = (string) $media->getAttribute('file_path');

        $this->repository->delete($media);
        Storage::disk('legacy_media')->delete($path);
    }

    private function store(int $wellId, array $upload): WellMedia
    {
        /** @var UploadedFile $file */
        $file = $upload['file'];
        $extension = $file->guessExtension() ?: $file->extension() ?: 'jpg';
        $path = $file->storeAs(
            "wells/{$wellId}",
            Str::uuid().'.'.$extension,
            'local',
        );

        if (! $path) {
            throw new RuntimeException('تعذر حفظ صورة البئر.');
        }

        try {
            return $this->repository->create([
                'WELL_OID' => $wellId,
                'CATEGORY' => $upload['category'],
                'LABEL' => $upload['label'] ?? null,
                'FILE_PATH' => $path,
                'ORIGINAL_NAME' => $file->getClientOriginalName(),
                'MIME_TYPE' => $file->getMimeType(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('legacy_media')->delete($path);

            throw $exception;
        }
    }
}
