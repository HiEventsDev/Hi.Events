<?php

namespace HiEvents\DomainObjects\Enums;

/**
 * Curated set of fonts available for public event and organizer homepages.
 * Kept in sync with frontend/src/constants/homepageFonts.ts — update both when adding/removing fonts.
 */
enum HomepageFontFamily: string
{
    use BaseEnum;

    case Outfit = 'Outfit';
    case Inter = 'Inter';
    case Roboto = 'Roboto';
    case OpenSans = 'Open Sans';
    case Poppins = 'Poppins';
    case Montserrat = 'Montserrat';
    case Lato = 'Lato';
    case Nunito = 'Nunito';
    case Raleway = 'Raleway';
    case DMSans = 'DM Sans';
    case PlusJakartaSans = 'Plus Jakarta Sans';
    case WorkSans = 'Work Sans';
    case SpaceGrotesk = 'Space Grotesk';
    case Manrope = 'Manrope';
    case Oswald = 'Oswald';
    case BebasNeue = 'Bebas Neue';
    case PlayfairDisplay = 'Playfair Display';
    case Merriweather = 'Merriweather';
    case Lora = 'Lora';
}
