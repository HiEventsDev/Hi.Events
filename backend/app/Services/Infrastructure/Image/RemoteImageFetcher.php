<?php

namespace HiEvents\Services\Infrastructure\Image;

use finfo;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use HiEvents\Exceptions\RemoteImageException;
use HiEvents\Exceptions\SsoException;
use HiEvents\Services\Domain\Auth\Sso\Network\SsrfGuard;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Throwable;

class RemoteImageFetcher
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const MAX_REDIRECTS = 3;

    public const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly Factory $http,
        private readonly SsrfGuard $ssrfGuard,
    ) {}

    /**
     * @throws RemoteImageException
     */
    public function fetch(string $url): UploadedFile
    {
        $response = $this->download($url);
        $body = $response->body();

        if (strlen($body) > self::MAX_BYTES) {
            throw new RemoteImageException(__('The image is larger than 5 MB.'));
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->buffer($body) ?: '';

        if (! isset(self::EXTENSIONS[$mimeType])) {
            throw new RemoteImageException(__('The URL must point to a JPEG, PNG or WebP image.'));
        }

        $path = tempnam(sys_get_temp_dir(), 'remote-image-');
        file_put_contents($path, $body);

        return new UploadedFile($path, 'image.'.self::EXTENSIONS[$mimeType], $mimeType, null, true);
    }

    /**
     * @throws RemoteImageException
     */
    private function download(string $url): Response
    {
        for ($redirect = 0; $redirect <= self::MAX_REDIRECTS; $redirect++) {
            $response = $this->get($url);

            if (! $response->redirect()) {
                if (! $response->successful()) {
                    throw new RemoteImageException(__('The image could not be downloaded (HTTP :status).', ['status' => $response->status()]));
                }

                return $response;
            }

            $url = (string) UriResolver::resolve(Utils::uriFor($url), Utils::uriFor((string) $response->header('Location')));
        }

        throw new RemoteImageException(__('The image URL redirects too many times.'));
    }

    /**
     * @throws RemoteImageException
     */
    private function get(string $url): Response
    {
        try {
            $target = $this->ssrfGuard->pin($url);
        } catch (SsoException) {
            throw new RemoteImageException(__('The image URL must be a public https address.'));
        }

        try {
            return $this->http
                ->connectTimeout(3)
                ->timeout(10)
                ->withOptions([
                    'allow_redirects' => false,
                    'curl' => [CURLOPT_RESOLVE => [$target->curlResolveEntry()]],
                    'progress' => static function (int $downloadTotal, int $downloaded): void {
                        if ($downloadTotal > self::MAX_BYTES || $downloaded > self::MAX_BYTES) {
                            throw new RemoteImageException(__('The image is larger than 5 MB.'));
                        }
                    },
                ])
                ->get($url);
        } catch (Throwable $exception) {
            throw $this->remoteImageException($exception);
        }
    }

    private function remoteImageException(Throwable $exception): RemoteImageException
    {
        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof RemoteImageException) {
                return $cause;
            }
        }

        return new RemoteImageException(__('The image could not be downloaded.'));
    }
}
