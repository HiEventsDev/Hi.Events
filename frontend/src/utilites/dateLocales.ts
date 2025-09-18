/**
 * Day.js locale configuration for internationalized date formatting
 */

import dayjs from 'dayjs';
import { SupportedLocales } from '../locales.ts';

// Import Day.js locales
import 'dayjs/locale/en';
import 'dayjs/locale/de';
import 'dayjs/locale/fr';
import 'dayjs/locale/it';
import 'dayjs/locale/nl';
import 'dayjs/locale/pt';
import 'dayjs/locale/es';
import 'dayjs/locale/zh-cn';
import 'dayjs/locale/pt-br';
import 'dayjs/locale/vi';
import 'dayjs/locale/zh-hk';

/**
 * Maps our supported locales to Day.js locale codes
 */
export const localeToDatejsLocale: Record<SupportedLocales, string> = {
    'en': 'en',
    'de': 'de',
    'fr': 'fr',
    'it': 'it',
    'nl': 'nl',
    'pt': 'pt',
    'es': 'es',
    'zh-cn': 'zh-cn',
    'pt-br': 'pt-br',
    'vi': 'vi',
    'zh-hk': 'zh-hk',
};

/**
 * Locale-specific date formats following cultural conventions
 */
export const localeFormats: Record<SupportedLocales, {
    fullDateTime: string;
    dayName: string;
    timeOnly: string;
    timezone: string;
}> = {
    'en': {
        fullDateTime: 'ddd, MMM D, YYYY h:mm A',
        dayName: 'dddd, MMMM D',
        timeOnly: 'h:mm A',
        timezone: 'z'
    },
    'de': {
        fullDateTime: 'ddd, D. MMM YYYY HH:mm',
        dayName: 'dddd, D. MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'fr': {
        fullDateTime: 'ddd D MMM YYYY HH:mm',
        dayName: 'dddd D MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'it': {
        fullDateTime: 'ddd D MMM YYYY HH:mm',
        dayName: 'dddd D MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'nl': {
        fullDateTime: 'ddd D MMM YYYY HH:mm',
        dayName: 'dddd D MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'pt': {
        fullDateTime: 'ddd, D [de] MMM [de] YYYY HH:mm',
        dayName: 'dddd, D [de] MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'es': {
        fullDateTime: 'ddd, D [de] MMM [de] YYYY HH:mm',
        dayName: 'dddd, D [de] MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'zh-cn': {
        fullDateTime: 'YYYY年M月D日 ddd HH:mm',
        dayName: 'M月D日 dddd',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'pt-br': {
        fullDateTime: 'ddd, D [de] MMM [de] YYYY HH:mm',
        dayName: 'dddd, D [de] MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'vi': {
        fullDateTime: 'ddd, [ngày] D [tháng] M [năm] YYYY HH:mm',
        dayName: '[ngày] D [tháng] M',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'zh-hk': {
        fullDateTime: 'YYYY年M月D日 ddd HH:mm',
        dayName: 'M月D日 dddd',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
};

/**
 * Sets the Day.js locale globally
 */
export const setDayjsLocale = (locale: SupportedLocales): void => {
    const dayjsLocale = localeToDatejsLocale[locale];
    if (dayjsLocale) {
        dayjs.locale(dayjsLocale);
    }
};