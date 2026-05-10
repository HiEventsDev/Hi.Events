<?php

namespace HiEvents\DomainObjects\Enums;

enum EventCategory: string
{
    use BaseEnum;

    // Community
    case SOCIAL = 'SOCIAL';
    case FAMILY = 'FAMILY';
    case HOBBIES = 'HOBBIES';
    case FOOD_DRINK = 'FOOD_DRINK';
    case WELLNESS = 'WELLNESS';
    case SPIRITUALITY = 'SPIRITUALITY';
    case OUTDOORS = 'OUTDOORS';
    case TOURS = 'TOURS';
    case CHARITY = 'CHARITY';

    // Creative & Culture
    case MUSIC = 'MUSIC';
    case ART = 'ART';
    case COMEDY = 'COMEDY';
    case THEATER = 'THEATER';
    case FILM = 'FILM';
    case DANCE = 'DANCE';

    // Professional & Learning
    case BUSINESS = 'BUSINESS';
    case TECH = 'TECH';
    case EDUCATION = 'EDUCATION';
    case WORKSHOP = 'WORKSHOP';

    // Leisure & Nightlife
    case SPORTS = 'SPORTS';
    case FESTIVAL = 'FESTIVAL';
    case SEASONAL = 'SEASONAL';
    case NIGHTLIFE = 'NIGHTLIFE';

    // Catch-all
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::SOCIAL => __('Social'),
            self::FAMILY => __('Family'),
            self::HOBBIES => __('Hobbies'),
            self::FOOD_DRINK => __('Food & Drink'),
            self::WELLNESS => __('Wellness'),
            self::SPIRITUALITY => __('Spirituality'),
            self::OUTDOORS => __('Outdoors'),
            self::TOURS => __('Tours'),
            self::CHARITY => __('Charity'),
            self::MUSIC => __('Music'),
            self::ART => __('Art'),
            self::COMEDY => __('Comedy'),
            self::THEATER => __('Theater'),
            self::FILM => __('Film'),
            self::DANCE => __('Dance'),
            self::BUSINESS => __('Business'),
            self::TECH => __('Tech'),
            self::EDUCATION => __('Education'),
            self::WORKSHOP => __('Workshop'),
            self::SPORTS => __('Sports'),
            self::FESTIVAL => __('Festival'),
            self::SEASONAL => __('Seasonal'),
            self::NIGHTLIFE => __('Nightlife'),
            self::OTHER => __('Other'),
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::SOCIAL => '🤝',
            self::FAMILY => '👨‍👩‍👧‍👦',
            self::HOBBIES => '🧩',
            self::FOOD_DRINK => '🍽️',
            self::WELLNESS => '🧘',
            self::SPIRITUALITY => '🙏',
            self::OUTDOORS => '🏞️',
            self::TOURS => '🗺️',
            self::CHARITY => '🎗️',
            self::MUSIC => '🎵',
            self::ART => '🎨',
            self::COMEDY => '😂',
            self::THEATER => '🎭',
            self::FILM => '🎬',
            self::DANCE => '💃',
            self::BUSINESS => '💼',
            self::TECH => '💻',
            self::EDUCATION => '📚',
            self::WORKSHOP => '🛠️',
            self::SPORTS => '⚽',
            self::FESTIVAL => '🎪',
            self::SEASONAL => '🎊',
            self::NIGHTLIFE => '🪩',
            self::OTHER => '🤔',
        };
    }
}
