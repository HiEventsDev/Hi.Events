<?php

namespace Tests\Unit\DomainObjects\Enums;

use HiEvents\DomainObjects\Enums\ProfileLinkType;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileLinkTypeTest extends TestCase
{
    public static function urlProvider(): array
    {
        return [
            ['https://open.spotify.com/artist/123', ProfileLinkType::SPOTIFY],
            ['https://music.apple.com/ie/artist/abc/1', ProfileLinkType::APPLE_MUSIC],
            ['https://soundcloud.com/nova', ProfileLinkType::SOUNDCLOUD],
            ['https://nova.bandcamp.com', ProfileLinkType::BANDCAMP],
            ['https://www.youtube.com/@nova', ProfileLinkType::YOUTUBE],
            ['https://youtu.be/abc', ProfileLinkType::YOUTUBE],
            ['https://www.instagram.com/nova', ProfileLinkType::INSTAGRAM],
            ['https://www.tiktok.com/@nova', ProfileLinkType::TIKTOK],
            ['https://twitter.com/nova', ProfileLinkType::X],
            ['https://x.com/nova', ProfileLinkType::X],
            ['https://m.facebook.com/nova', ProfileLinkType::FACEBOOK],
            ['https://www.linkedin.com/in/ada', ProfileLinkType::LINKEDIN],
            ['https://github.com/ada', ProfileLinkType::GITHUB],
            ['https://nova.example.com', ProfileLinkType::WEBSITE],
            ['https://notspotify.com/artist', ProfileLinkType::WEBSITE],
            ['https://apple.com/music', ProfileLinkType::WEBSITE],
        ];
    }

    #[DataProvider('urlProvider')]
    public function test_detects_link_type_from_url(string $url, ProfileLinkType $expected): void
    {
        $this->assertSame($expected, ProfileLinkType::fromUrl($url));
    }
}
