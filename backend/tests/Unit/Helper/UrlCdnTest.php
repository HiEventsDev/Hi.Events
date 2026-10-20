<?php

namespace Tests\Unit\Helper;

use HiEvents\Helper\Url;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UrlCdnTest extends TestCase
{
    public function test_it_uses_the_configured_cdn_url(): void
    {
        config(['app.cdn_url' => 'https://cdn.example.com']);

        $this->assertSame('https://cdn.example.com/images/a.png', Url::getCdnUrl('images/a.png'));
    }

    public static function unsetCdnUrls(): array
    {
        return [
            'unset' => [null],
            'blank' => [''],
        ];
    }

    #[DataProvider('unsetCdnUrls')]
    public function test_it_falls_back_to_the_public_disk_url_when_no_cdn_url_is_configured(?string $cdnUrl): void
    {
        config(['app.cdn_url' => $cdnUrl, 'filesystems.public' => 's3-public']);

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('url')->with('images/a.png')->andReturn('https://bucket.example.com/images/a.png');

        $filesystem = Mockery::mock(Factory::class);
        $filesystem->shouldReceive('disk')->with('s3-public')->andReturn($disk);
        $this->app->instance('filesystem', $filesystem);

        $this->assertSame('https://bucket.example.com/images/a.png', Url::getCdnUrl('images/a.png'));
    }
}
