<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Models\Wells\WellMedia;
use App\Repositories\Wells\WellMediaRepository;
use App\Services\Wells\WellMediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class WellMediaServiceTest extends TestCase
{
    public function test_it_stores_a_named_well_image_in_private_storage(): void
    {
        Storage::fake('local');
        $repository = Mockery::mock(WellMediaRepository::class);
        $repository->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data): bool => $data['WELL_OID'] === 14
                && $data['CATEGORY'] === 'pump'
                && $data['LABEL'] === 'المضخة الرئيسية'
                && str_starts_with($data['FILE_PATH'], 'wells/14/'))
            ->andReturn(new WellMedia);

        (new WellMediaService($repository))->storeMany(14, [[
            'category' => 'pump',
            'label' => 'المضخة الرئيسية',
            'file' => UploadedFile::fake()->create('pump.jpg', 100, 'image/jpeg'),
        ]]);

        self::assertCount(1, Storage::disk('local')->allFiles('wells/14'));
    }
}
