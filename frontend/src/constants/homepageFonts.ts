export interface HomepageFontDefinition {
    value: string;
    label: string;
    category: 'sans' | 'display' | 'serif';
    bunnyFamily: string;
    weights: string;
    stack: string;
}

const sansStack = `-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif`;
const serifStack = `Georgia, 'Times New Roman', Times, serif`;
const displayStack = `Impact, 'Helvetica Neue', sans-serif`;

/**
 * Curated set of fonts offered in the homepage designer.
 * Kept in sync with backend/app/DomainObjects/Enums/HomepageFontFamily.php —
 * update both when adding or removing fonts.
 */
export const HOMEPAGE_FONTS: HomepageFontDefinition[] = [
    {value: 'Outfit', label: 'Outfit', category: 'sans', bunnyFamily: 'outfit', weights: '400,500,600,700,800', stack: sansStack},
    {value: 'Inter', label: 'Inter', category: 'sans', bunnyFamily: 'inter', weights: '400,500,600,700,800', stack: sansStack},
    {value: 'Roboto', label: 'Roboto', category: 'sans', bunnyFamily: 'roboto', weights: '400,500,700', stack: sansStack},
    {value: 'Open Sans', label: 'Open Sans', category: 'sans', bunnyFamily: 'open-sans', weights: '400,500,600,700,800', stack: sansStack},
    {value: 'Poppins', label: 'Poppins', category: 'sans', bunnyFamily: 'poppins', weights: '400,500,600,700,800', stack: sansStack},
    {value: 'Montserrat', label: 'Montserrat', category: 'sans', bunnyFamily: 'montserrat', weights: '400,500,600,700,800', stack: sansStack},
    {value: 'Lato', label: 'Lato', category: 'sans', bunnyFamily: 'lato', weights: '400,700,900', stack: sansStack},
    {value: 'Nunito', label: 'Nunito', category: 'sans', bunnyFamily: 'nunito', weights: '400,500,600,700,800', stack: sansStack},
    {value: 'Raleway', label: 'Raleway', category: 'sans', bunnyFamily: 'raleway', weights: '400,500,600,700,800', stack: sansStack},
    {value: 'DM Sans', label: 'DM Sans', category: 'sans', bunnyFamily: 'dm-sans', weights: '400,500,700', stack: sansStack},
    {value: 'Plus Jakarta Sans', label: 'Plus Jakarta Sans', category: 'sans', bunnyFamily: 'plus-jakarta-sans', weights: '400,500,600,700,800', stack: sansStack},
    {value: 'Work Sans', label: 'Work Sans', category: 'sans', bunnyFamily: 'work-sans', weights: '400,500,600,700,800', stack: sansStack},
    {value: 'Space Grotesk', label: 'Space Grotesk', category: 'sans', bunnyFamily: 'space-grotesk', weights: '400,500,600,700', stack: sansStack},
    {value: 'Manrope', label: 'Manrope', category: 'sans', bunnyFamily: 'manrope', weights: '400,500,600,700,800', stack: sansStack},
    {value: 'Oswald', label: 'Oswald', category: 'display', bunnyFamily: 'oswald', weights: '400,500,600,700', stack: displayStack},
    {value: 'Bebas Neue', label: 'Bebas Neue', category: 'display', bunnyFamily: 'bebas-neue', weights: '400', stack: displayStack},
    {value: 'Playfair Display', label: 'Playfair Display', category: 'serif', bunnyFamily: 'playfair-display', weights: '400,500,600,700,800', stack: serifStack},
    {value: 'Merriweather', label: 'Merriweather', category: 'serif', bunnyFamily: 'merriweather', weights: '400,700,900', stack: serifStack},
    {value: 'Lora', label: 'Lora', category: 'serif', bunnyFamily: 'lora', weights: '400,500,600,700', stack: serifStack},
];

export const DEFAULT_HOMEPAGE_FONT = 'Outfit';

const FONT_LOOKUP: Record<string, HomepageFontDefinition> = HOMEPAGE_FONTS.reduce(
    (acc, font) => ({...acc, [font.value]: font}),
    {} as Record<string, HomepageFontDefinition>,
);

export const getHomepageFont = (value: string | null | undefined): HomepageFontDefinition => {
    if (value && FONT_LOOKUP[value]) {
        return FONT_LOOKUP[value];
    }
    return FONT_LOOKUP[DEFAULT_HOMEPAGE_FONT];
};

export const buildHomepageFontStack = (value: string | null | undefined): string => {
    const font = getHomepageFont(value);
    return `'${font.value}', ${font.stack}`;
};

export const buildHomepageFontUrl = (value: string | null | undefined): string => {
    const font = getHomepageFont(value);
    return `https://fonts.bunny.net/css?family=${font.bunnyFamily}:${font.weights}&display=swap`;
};
