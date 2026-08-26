<?php

declare(strict_types=1);

namespace App\Services\Wells;

use App\Models\Wells\WellActivityMedia;
use App\Repositories\Wells\ActivityMediaRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityMediaService
{
    public const TYPE_READING = 'reading';

    public const TYPE_NOTIFICATION = 'notification';

    public function __construct(
        private readonly ActivityMediaRepository $repository,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, string>  $clientLocalIds
     * @return array<int, WellActivityMedia>
     */
    public function storeMany(
        string $activityType,
        int $activityId,
        array $files,
        array $clientLocalIds,
    ): array {
        $stored = [];

        foreach ($files as $index => $file) {
            $stored[] = $this->store(
                $activityType,
                $activityId,
                $file,
                $clientLocalIds[$index],
            );
        }

        return $stored;
    }

    public function response(int $id): StreamedResponse
    {
        $media = $this->repository->findOrFail($id);
        $path = (string) $media->getAttribute('file_path');

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response(
            $path,
            (string) $media->getAttribute('original_name'),
            [
                'Content-Type' => (string) $media->getAttribute('mime_type'),
                'Cache-Control' => 'private, max-age=86400',
            ],
        );
    }

    private function store(
        string $activityType,
        int $activityId,
        UploadedFile $file,
        string $clientLocalId,
    ): WellActivityMedia {
        $existing = $this->repository->findByClientLocalId($clientLocalId);

        if ($existing !== null) {
            abort_unless(
                (string) ($existing->getAttribute('activity_type')
                    ?? $existing->getAttribute('ACTIVITY_TYPE')) === $activityType
                && (int) ($existing->getAttribute('activity_oid')
                    ?? $existing->getAttribute('ACTIVITY_OID')) === $activityId,
                409,
                'معرف الصورة مستخدم لنشاط آخر.',
            );

            return $existing;
        }

        $extension = $file->guessExtension() ?: $file->extension() ?: 'jpg';
        $path = $file->storeAs(
            "wells/activity-media/{$activityType}/{$activityId}",
            Str::uuid().'.'.$extension,
            'local',
        );

        if (! $path) {
            throw new RuntimeException('تعذر حفظ صورة النشاط.');
        }

        try {
            return $this->repository->create([
                'ACTIVITY_TYPE' => $activityType,
                'ACTIVITY_OID' => $activityId,
                'CLIENT_LOCAL_ID' => $clientLocalId,
                'FILE_PATH' => $path,
                'ORIGINAL_NAME' => $file->getClientOriginalName(),
                'MIME_TYPE' => $file->getMimeType() ?: 'image/jpeg',
                'FILE_SIZE' => $file->getSize(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }
}
