<?php

namespace HiEvents\DomainObjects\Enums;

enum ProfileLinkType: string
{
    use BaseEnum;

    case SPOTIFY = 'SPOTIFY';
    case APPLE_MUSIC = 'APPLE_MUSIC';
    case SOUNDCLOUD = 'SOUNDCLOUD';
    case BANDCAMP = 'BANDCAMP';
    case YOUTUBE = 'YOUTUBE';
    case INSTAGRAM = 'INSTAGRAM';
    case TIKTOK = 'TIKTOK';
    case X = 'X';
    case FACEBOOK = 'FACEBOOK';
    case LINKEDIN = 'LINKEDIN';
    case GITHUB = 'GITHUB';
    case WEBSITE = 'WEBSITE';

    public static function fromUrl(string $url): self
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return match (true) {
            self::hostMatches($host, ['spotify.com', 'spotify.link']) => self::SPOTIFY,
            self::hostMatches($host, ['music.apple.com']) => self::APPLE_MUSIC,
            self::hostMatches($host, ['soundcloud.com']) => self::SOUNDCLOUD,
            self::hostMatches($host, ['bandcamp.com']) => self::BANDCAMP,
            self::hostMatches($host, ['youtube.com', 'youtu.be']) => self::YOUTUBE,
            self::hostMatches($host, ['instagram.com']) => self::INSTAGRAM,
            self::hostMatches($host, ['tiktok.com']) => self::TIKTOK,
            self::hostMatches($host, ['x.com', 'twitter.com']) => self::X,
            self::hostMatches($host, ['facebook.com', 'fb.com']) => self::FACEBOOK,
            self::hostMatches($host, ['linkedin.com']) => self::LINKEDIN,
            self::hostMatches($host, ['github.com']) => self::GITHUB,
            default => self::WEBSITE,
        };
    }

    /**
     * @param  string[]  $urls
     * @return array<int, array{type: string, url: string}>
     */
    public static function linksFromUrls(array $urls): array
    {
        return collect($urls)
            ->map(fn (string $url) => trim($url))
            ->filter()
            ->unique()
            ->values()
            ->map(fn (string $url) => [
                'type' => self::fromUrl($url)->value,
                'url' => $url,
            ])
            ->all();
    }

    private static function hostMatches(string $host, array $domains): bool
    {
        foreach ($domains as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }
}
