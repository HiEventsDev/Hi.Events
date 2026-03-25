<?php

namespace HiEvents\DomainObjects\Enums;

enum EventCategory: string
{
    use BaseEnum;

    // Community
    case TECHNO = 'TECHNO';
    case HARDSTYLE = 'HARDSTYLE';
    case CHARITY = 'CHARITY';

    // Creative & Culture
    case MUSIC = 'MUSIC';
    case ART = 'ART';
    case COMEDY = 'COMEDY';
    case THEATER = 'THEATER';

    // Professional & Learning
    case BUSINESS = 'BUSINESS';
    case TECH = 'TECH';
    case EDUCATION = 'EDUCATION';
    case WORKSHOP = 'WORKSHOP';

    // Leisure & Nightlife
    case SPORTS = 'SPORTS';
    case FESTIVAL = 'FESTIVAL';
    case NIGHTLIFE = 'NIGHTLIFE';

    // Catch-all
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::TECHNO => __('Techno'),
            self::HARDSTYLE => __('Hardtsyle'),
            self::CHARITY => __('Charity'),
            self::MUSIC => __('Music'),
            self::ART => __('Art'),
            self::COMEDY => __('Comedy'),
            self::THEATER => __('Theater'),
            self::BUSINESS => __('Business'),
            self::TECH => __('Tech'),
            self::EDUCATION => __('Education'),
            self::WORKSHOP => __('Workshop'),
            self::SPORTS => __('Sports'),
            self::FESTIVAL => __('Festival'),
            self::NIGHTLIFE => __('Nightlife'),
            self::OTHER => __('Other'),
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::TECHNO => '🤝',
            self::HARDSTYLE => '🍽️',
            self::CHARITY => '🎗️',
            self::MUSIC => '🎵',
            self::ART => '🎨',
            self::COMEDY => '😂',
            self::THEATER => '🎭',
            self::BUSINESS => '💼',
            self::TECH => '💻',
            self::EDUCATION => '📚',
            self::WORKSHOP => '🛠️',
            self::SPORTS => '⚽',
            self::FESTIVAL => '🎉',
            self::NIGHTLIFE => '🪩',
            self::OTHER => '📝',
        };
    }
}
