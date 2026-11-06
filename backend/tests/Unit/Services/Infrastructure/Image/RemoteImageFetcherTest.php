<?php

namespace Tests\Unit\Services\Infrastructure\Image;

use HiEvents\Exceptions\RemoteImageException;
use HiEvents\Services\Domain\Auth\Sso\Network\HostResolver;
use HiEvents\Services\Domain\Auth\Sso\Network\SsrfGuard;
use HiEvents\Services\Infrastructure\Image\RemoteImageFetcher;
use Illuminate\Http\Client\Factory;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class RemoteImageFetcherTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private Factory $http;

    private HostResolver&MockInterface $resolver;

    private RemoteImageFetcher $fetcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = new Factory;
        $this->resolver = Mockery::mock(HostResolver::class);
        $this->resolver->shouldReceive('resolve')->byDefault()->andReturn(['93.184.216.34']);
        $this->fetcher = new RemoteImageFetcher($this->http, new SsrfGuard($this->resolver));
    }

    public function test_an_image_is_saved_to_a_temporary_upload_with_its_real_type(): void
    {
        $this->http->fake(['https://images.example.com/*' => $this->http->response(base64_decode(self::PNG), 200, ['Content-Type' => 'text/plain'])]);

        $file = $this->fetcher->fetch('https://images.example.com/poster');

        $this->assertSame('image/png', $file->getClientMimeType());
        $this->assertSame('png', $file->getClientOriginalExtension());
        $this->assertFileExists($file->getPathname());
        unlink($file->getPathname());
    }

    public function test_redirects_are_followed_and_each_hop_is_checked(): void
    {
        $this->http->fake([
            'https://short.example.com/*' => $this->http->response('', 302, ['Location' => 'https://cdn.example.com/real.png']),
            'https://cdn.example.com/*' => $this->http->response(base64_decode(self::PNG), 200),
        ]);
        $this->resolver->shouldReceive('resolve')->with('short.example.com')->andReturn(['93.184.216.34']);
        $this->resolver->shouldReceive('resolve')->with('cdn.example.com')->andReturn(['10.0.0.5']);

        $this->expectException(RemoteImageException::class);

        $this->fetcher->fetch('https://short.example.com/x');
    }

    public function test_too_many_redirects_are_refused(): void
    {
        $this->http->fake(['*' => $this->http->response('', 302, ['Location' => '/again'])]);

        $this->expectExceptionMessage('redirects too many times');

        $this->fetcher->fetch('https://loop.example.com/start');
    }

    public function test_non_https_and_private_addresses_are_refused_without_a_request(): void
    {
        $this->http->fake();
        $this->resolver->shouldReceive('resolve')->with('internal.example.com')->andReturn(['127.0.0.1']);

        foreach (['http://images.example.com/a.png', 'https://internal.example.com/a.png', 'file:///etc/passwd'] as $url) {
            try {
                $this->fetcher->fetch($url);
                $this->fail("$url was fetched");
            } catch (RemoteImageException $exception) {
                $this->assertStringContainsString('public https', $exception->getMessage());
            }
        }

        $this->http->assertNothingSent();
    }

    public function test_files_that_are_not_images_are_refused(): void
    {
        $this->http->fake(['*' => $this->http->response('<html>hello</html>', 200, ['Content-Type' => 'image/png'])]);

        $this->expectExceptionMessage('JPEG, PNG or WebP');

        $this->fetcher->fetch('https://images.example.com/fake.png');
    }

    public function test_oversized_downloads_are_refused(): void
    {
        $this->http->fake(['*' => $this->http->response(str_repeat('a', RemoteImageFetcher::MAX_BYTES + 1), 200)]);

        $this->expectExceptionMessage('larger than 5 MB');

        $this->fetcher->fetch('https://images.example.com/huge.png');
    }

    public function test_error_responses_are_reported_with_their_status(): void
    {
        $this->http->fake(['*' => $this->http->response('', 404)]);

        $this->expectExceptionMessage('HTTP 404');

        $this->fetcher->fetch('https://images.example.com/missing.png');
    }
}
