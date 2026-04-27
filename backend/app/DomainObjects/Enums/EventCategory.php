<?php

namespace HiEvents\DomainObjects\Enums;

enum EventCategory: string
{
    use BaseEnum;
    
    case BASSHOUSE = 'BASSHOUSE';
    case BOUNCE = 'BOUNCE';
    case BOUNCYTECHNO = 'BOUNCYTECHNO';
    case DRUMBASS = 'DRUMBASS';
    case EDM = 'EDM';
    case GOA = 'GOA';
    case GROOVEBOUNCE = 'GROOVEBOUNCE';
    case HARDTECHNO = 'HARDTECHNO';
    case HARDCOREUPTEMPO = 'HARDCOREUPTEMPO';
    case HARDSTYLE = 'HARDSTYLE';
    case RAWSTYLE = 'RAWSTYLE';
    case HOUSEMUSIC = 'HOUSEMUSIC';
    case MELODICHOUSE = 'MELODICHOUSE';
    case MIXEDMUSIC = 'MIXEDMUSIC';
    case NEOTRANCE = 'NEOTRANCE';
    case SCHRANZ = 'SCHRANZ';
    case TECHHOUSE = 'TECHHOUSE';
    case TECHNO = 'TECHNO';
    case TEKK = 'TEKK';
    case TRANCEMUSIC = 'TRANCEMUSIC';
    case UKG = 'UKG';
    case WORKSHOP = 'WORKSHOP';
    case INDUSTRIAL = 'INDUSTRIAL';

    // Catch-all
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::BASSHOUSE => __('BASS HOUSE'),
            self::BOUNCE => __('BOUNCE'),
            self::BOUNCYTECHNO => __('BOUNCY TECHNO'),
            self::DRUMBASS => __('DRUM & BASS'),
            self::EDM => __('EDM'),
            self::GOA => __('GOA'),
            self::GROOVEBOUNCE => __('GROOVE & BOUNCE'),
            self::HARDTECHNO => __('HARDTECHNO'),
            self::HARDCOREUPTEMPO => __('HARDCORE & UPTEMPO'),
            self::HARDSTYLE => __('HARDSTYLE'),
            self::RAWSTYLE => __('RAWSTYLE'),
            self::HOUSEMUSIC => __('HOUSE MUSIC'),
            self::MELODICHOUSE => __('MELODIC HOUSE'),
            self::MIXEDMUSIC => __('MIXED MUSIC'),
            self::NEOTRANCE => __('NEOTRANCE'),
            self::SCHRANZ => __('SCHRANZ'),
            self::TECHHOUSE => __('TECH HOUSE'),
            self::TECHNO => __('TECHNO'),
            self::TEKK => __('TEKK'),
            self::TRANCEMUSIC => __('TRANCE MUSIC'),
            self::UKG => __('UKG'),
            self::WORKSHOP => __('WORKSHOP'),
            self::INDUSTRIAL => __('INDUSTRIAL'),
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::BASSHOUSE => '🎵',
            self::BOUNCE => '🎵',
            self::BOUNCYTECHNO => '🎵',
            self::DRUMBASS => '🎵',
            self::EDM => '🎵',
            self::GOA => '🎵',
            self::GROOVEBOUNCE => '🎵',
            self::HARDTECHNO => '🎵',
            self::HARDCOREUPTEMPO => '🎵',
            self::HARDSTYLE => '🎵',
            self::RAWSTYLE => '🎵',
            self::HOUSEMUSIC => '🎵',
            self::MELODICHOUSE => '🎵',
            self::MIXEDMUSIC => '🎵',
            self::NEOTRANCE => '🎵',
            self::SCHRANZ => '🎵',
            self::TECHHOUSE => '🎵',
            self::TECHNO => '🎵',
            self::TEKK => '🎵',
            self::TRANCEMUSIC => '🎵',
            self::UKG => '🎵',
            self::WORKSHOP => '🎵',
            self::INDUSTRIAL => '🎵',
        };
    }
}
