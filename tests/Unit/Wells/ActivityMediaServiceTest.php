<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Models\Wells\WellActivityMedia;
use App\Repositories\Wells\ActivityMediaRepository;
use App\Services\Wells\ActivityMediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ActivityMediaServiceTest extends TestCase
{
    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageRoot = sys_get_temp_dir().'/wells-activity-media-tests';
        File::deleteDirectory($this->storageRoot);
        File::ensureDirectoryExists($this->storageRoot);
        config(['filesystems.disks.local.root' => $this->storageRoot]);
        Storage::forgetDisk('local');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storageRoot);
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_stores_compressed_activity_images_in_private_storage(): void
    {
        $repository = Mockery::mock(ActivityMediaRepository::class);
        $repository->shouldReceive('findByClientLocalId')
            ->once()
            ->with('236fc94f-6709-4d4b-9b8e-66843d23d2a9')
            ->andReturnNull();
        $repository->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data): bool => $data['ACTIVITY_TYPE'] === 'reading'
                && $data['ACTIVITY_OID'] === 14
                && $data['CLIENT_LOCAL_ID'] === '236fc94f-6709-4d4b-9b8e-66843d23d2a9'
                && str_starts_with(
                    $data['FILE_PATH'],
                    'wells/activity-media/reading/14/',
                ))
            ->andReturn(new WellActivityMedia);

        (new ActivityMediaService($repository))->storeMany(
            ActivityMediaService::TYPE_READING,
            14,
            [UploadedFile::fake()->create('reading.jpg', 800, 'image/jpeg')],
            ['236fc94f-6709-4d4b-9b8e-66843d23d2a9'],
        );

        self::assertCount(
            1,
            Storage::disk('local')->allFiles(
                'wells/activity-media/reading/14',
            ),
        );
    }

    public function test_retry_reuses_existing_client_photo_without_duplicate_file(): void
    {
        $existing = new WellActivityMedia;
        $repository = Mockery::mock(ActivityMediaRepository::class);
        $repository->shouldReceive('findByClientLocalId')
            ->once()
            ->andReturn($existing);
        $repository->shouldNotReceive('create');

        $result = (new ActivityMediaService($repository))->storeMany(
            ActivityMediaService::TYPE_NOTIFICATION,
            22,
            [UploadedFile::fake()->create('signal.jpg', 700, 'image/jpeg')],
            ['236fc94f-6709-4d4b-9b8e-66843d23d2a9'],
        );

        self::assertSame($existing, $result[0]);
        self::assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_dashboard_media_url_is_relative_to_the_current_host(): void
    {
        $media = new WellActivityMedia;
        $media->setAttribute('oid', 17);

        self::assertSame('/wells/activity-media/17', $media->url);
    }
}
