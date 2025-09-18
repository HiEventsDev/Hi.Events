/**
 * Example usage of the localized date formatting system
 * 
 * This file demonstrates how dates will be formatted differently based on user locale.
 * The EventDateRange component automatically uses the user's preferred locale.
 */

import { formatDateWithLocale, formatDateForUser } from '../utilites/dates.ts';
import { SupportedLocales } from '../locales.ts';

// Example date and timezone for demonstration
const exampleDate = '2024-06-15T19:30:00Z';
const timezone = 'America/New_York';

/**
 * Example of how dates are formatted for different locales
 */
export const dateFormattingExamples: Record<SupportedLocales, {
    fullDateTime: string;
    dayName: string;
    timeOnly: string;
}> = {
    'en': {
        fullDateTime: formatDateWithLocale(exampleDate, 'fullDateTime', timezone, 'en'),
        // Example output: "Sat, Jun 15, 2024 3:30 PM"
        dayName: formatDateWithLocale(exampleDate, 'dayName', timezone, 'en'),
        // Example output: "Saturday, June 15"
        timeOnly: formatDateWithLocale(exampleDate, 'timeOnly', timezone, 'en'),
        // Example output: "3:30 PM"
    },
    'de': {
        fullDateTime: formatDateWithLocale(exampleDate, 'fullDateTime', timezone, 'de'),
        // Example output: "Sa, 15. Jun 2024 15:30"
        dayName: formatDateWithLocale(exampleDate, 'dayName', timezone, 'de'),
        // Example output: "Samstag, 15. Juni"
        timeOnly: formatDateWithLocale(exampleDate, 'timeOnly', timezone, 'de'),
        // Example output: "15:30"
    },
    'fr': {
        fullDateTime: formatDateWithLocale(exampleDate, 'fullDateTime', timezone, 'fr'),
        // Example output: "sam 15 juin 2024 15:30"
        dayName: formatDateWithLocale(exampleDate, 'dayName', timezone, 'fr'),
        // Example output: "samedi 15 juin"
        timeOnly: formatDateWithLocale(exampleDate, 'timeOnly', timezone, 'fr'),
        // Example output: "15:30"
    },
    'es': {
        fullDateTime: formatDateWithLocale(exampleDate, 'fullDateTime', timezone, 'es'),
        // Example output: "sáb, 15 de jun de 2024 15:30"
        dayName: formatDateWithLocale(exampleDate, 'dayName', timezone, 'es'),
        // Example output: "sábado, 15 de junio"
        timeOnly: formatDateWithLocale(exampleDate, 'timeOnly', timezone, 'es'),
        // Example output: "15:30"
    },
    'zh-cn': {
        fullDateTime: formatDateWithLocale(exampleDate, 'fullDateTime', timezone, 'zh-cn'),
        // Example output: "2024年6月15日 六 15:30"
        dayName: formatDateWithLocale(exampleDate, 'dayName', timezone, 'zh-cn'),
        // Example output: "6月15日 星期六"
        timeOnly: formatDateWithLocale(exampleDate, 'timeOnly', timezone, 'zh-cn'),
        // Example output: "15:30"
    },
    'it': {
        fullDateTime: formatDateWithLocale(exampleDate, 'fullDateTime', timezone, 'it'),
        dayName: formatDateWithLocale(exampleDate, 'dayName', timezone, 'it'),
        timeOnly: formatDateWithLocale(exampleDate, 'timeOnly', timezone, 'it'),
    },
    'nl': {
        fullDateTime: formatDateWithLocale(exampleDate, 'fullDateTime', timezone, 'nl'),
        dayName: formatDateWithLocale(exampleDate, 'dayName', timezone, 'nl'),
        timeOnly: formatDateWithLocale(exampleDate, 'timeOnly', timezone, 'nl'),
    },
    'pt': {
        fullDateTime: formatDateWithLocale(exampleDate, 'fullDateTime', timezone, 'pt'),
        dayName: formatDateWithLocale(exampleDate, 'dayName', timezone, 'pt'),
        timeOnly: formatDateWithLocale(exampleDate, 'timeOnly', timezone, 'pt'),
    },
    'pt-br': {
        fullDateTime: formatDateWithLocale(exampleDate, 'fullDateTime', timezone, 'pt-br'),
        dayName: formatDateWithLocale(exampleDate, 'dayName', timezone, 'pt-br'),
        timeOnly: formatDateWithLocale(exampleDate, 'timeOnly', timezone, 'pt-br'),
    },
    'vi': {
        fullDateTime: formatDateWithLocale(exampleDate, 'fullDateTime', timezone, 'vi'),
        dayName: formatDateWithLocale(exampleDate, 'dayName', timezone, 'vi'),
        timeOnly: formatDateWithLocale(exampleDate, 'timeOnly', timezone, 'vi'),
    },
    'zh-hk': {
        fullDateTime: formatDateWithLocale(exampleDate, 'fullDateTime', timezone, 'zh-hk'),
        dayName: formatDateWithLocale(exampleDate, 'dayName', timezone, 'zh-hk'),
        timeOnly: formatDateWithLocale(exampleDate, 'timeOnly', timezone, 'zh-hk'),
    },
};

/**
 * Usage notes:
 * 
 * 1. The EventDateRange component automatically uses the user's locale from their profile settings
 * 2. If no user locale is available, it falls back to browser locale or cookies
 * 3. All dates respect the event's timezone for proper display
 * 4. The formatting follows cultural conventions for each locale:
 *    - English: 12-hour format with AM/PM
 *    - European languages: 24-hour format
 *    - Chinese: Traditional year-month-day format
 *    - Portuguese/Spanish: Use "de" preposition
 *    - Vietnamese: Use locale-specific prepositions
 */