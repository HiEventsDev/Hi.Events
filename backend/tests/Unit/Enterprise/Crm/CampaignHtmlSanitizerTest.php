<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignHtmlSanitizer;
use HiEvents\Helper\Url;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use HTMLPurifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignHtmlSanitizerTest extends TestCase
{
    private CampaignHtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = new CampaignHtmlSanitizer(new HtmlPurifierService(new HTMLPurifier));
    }

    public static function cssImages(): array
    {
        return [
            'list-style-image' => ['<ul style="list-style-image: url(https://evil.example/t.png)"><li>a</li></ul>'],
            'list-style shorthand' => ['<ul style="list-style: url(https://evil.example/t.png)"><li>a</li></ul>'],
            'list-style shorthand with a type' => ['<ul style="list-style: square url(https://evil.example/t.png)"><li>a</li></ul>'],
            'background shorthand' => ['<p style="background: url(https://evil.example/t.png)">a</p>'],
            'background-image' => ['<p style="background-image: url(https://evil.example/t.png)">a</p>'],
        ];
    }

    #[DataProvider('cssImages')]
    public function test_no_css_property_can_load_an_image(string $html): void
    {
        $sanitized = (string) $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('url(', $sanitized);
        $this->assertStringNotContainsString('evil.example', $sanitized);
        $this->assertStringContainsString('a', strip_tags($sanitized));
    }

    public function test_list_markers_without_an_image_are_kept(): void
    {
        $sanitized = (string) $this->sanitizer->sanitize('<ul style="list-style-type: square; list-style-position: inside"><li>a</li></ul>');

        $this->assertStringContainsString('list-style-type:square', $sanitized);
        $this->assertStringContainsString('list-style-position:inside', $sanitized);
    }

    public function test_an_image_from_our_storage_is_kept(): void
    {
        $image = '<img src="'.Url::getCdnBaseUrl().'campaign-images/photo.v2.png" alt="A photo" />';

        $this->assertStringContainsString('campaign-images/photo.v2.png', (string) $this->sanitizer->sanitize('<p>'.$image.'</p>'));
    }

    public static function pathsLeavingOurStorage(): array
    {
        return [
            'parent segment' => ['../other-bucket/x.png'],
            'nested parent segment' => ['campaign-images/../../other-bucket/x.png'],
            'current segment' => ['./x.png'],
            'encoded dots' => ['%2e%2e/other-bucket/x.png'],
            'mixed-case encoded dots' => ['.%2E/other-bucket/x.png'],
            'backslash' => ['..\\other-bucket\\x.png'],
            'encoded backslash' => ['..%5Cother-bucket%5Cx.png'],
            'encoded slash' => ['..%2Fother-bucket/x.png'],
        ];
    }

    #[DataProvider('pathsLeavingOurStorage')]
    public function test_an_image_whose_path_could_leave_our_storage_is_removed(string $path): void
    {
        $sanitized = (string) $this->sanitizer->sanitize('<p>before<img src="'.Url::getCdnBaseUrl().$path.'" alt="x" />after</p>');

        $this->assertStringNotContainsString('<img', $sanitized);
        $this->assertStringContainsString('beforeafter', $sanitized);
    }

    public function test_an_image_from_another_host_is_removed(): void
    {
        $sanitized = (string) $this->sanitizer->sanitize('<p><img src="https://evil.example/t.png" alt="x" /></p>');

        $this->assertStringNotContainsString('<img', $sanitized);
    }
}
