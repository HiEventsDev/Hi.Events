/**
 * Day.js locale configuration for internationalized date formatting
 */

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
import 'dayjs/locale/tr';
import 'dayjs/locale/hu';

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
    'tr': 'tr',
    'hu': 'hu',
};

/**
 * Locale-specific date formats following cultural conventions
 */
export const localeFormats: Record<SupportedLocales, {
    fullDateTime: string;
    shortDateTime: string;
    shortDate: string;
    chartDate: string;
    monthShort: string;
    dayOfMonth: string;
    dayName: string;
    timeOnly: string;
    timezone: string;
}> = {
    'en': {
        fullDateTime: 'ddd, MMM D, YYYY h:mm A',
        shortDateTime: 'MMM D, YYYY h:mma',
        shortDate: 'MMM D, YYYY',
        chartDate: 'MMM D',
        monthShort: 'MMM',
        dayOfMonth: 'D',
        dayName: 'dddd, MMMM D',
        timeOnly: 'h:mm A',
        timezone: 'z'
    },
    'de': {
        fullDateTime: 'ddd, D. MMM YYYY HH:mm',
        shortDateTime: 'D. MMM YYYY HH:mm',
        shortDate: 'D. MMM YYYY',
        chartDate: 'D. MMM',
        monthShort: 'MMM',
        dayOfMonth: 'D',
        dayName: 'dddd, D. MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'fr': {
        fullDateTime: 'ddd D MMM YYYY HH:mm',
        shortDateTime: 'D MMM YYYY HH:mm',
        shortDate: 'D MMM YYYY',
        chartDate: 'D MMM',
        monthShort: 'MMM',
        dayOfMonth: 'D',
        dayName: 'dddd D MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'it': {
        fullDateTime: 'ddd D MMM YYYY HH:mm',
        shortDateTime: 'D MMM YYYY HH:mm',
        shortDate: 'D MMM YYYY',
        chartDate: 'D MMM',
        monthShort: 'MMM',
        dayOfMonth: 'D',
        dayName: 'dddd D MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'nl': {
        fullDateTime: 'ddd D MMM YYYY HH:mm',
        shortDateTime: 'D MMM YYYY HH:mm',
        shortDate: 'D MMM YYYY',
        chartDate: 'D MMM',
        monthShort: 'MMM',
        dayOfMonth: 'D',
        dayName: 'dddd D MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'pt': {
        fullDateTime: 'ddd, D [de] MMM [de] YYYY HH:mm',
        shortDateTime: 'D [de] MMM [de] YYYY HH:mm',
        shortDate: 'D [de] MMM [de] YYYY',
        chartDate: 'D [de] MMM',
        monthShort: 'MMM',
        dayOfMonth: 'D',
        dayName: 'dddd, D [de] MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'es': {
        fullDateTime: 'ddd, D [de] MMM [de] YYYY HH:mm',
        shortDateTime: 'D [de] MMM [de] YYYY HH:mm',
        shortDate: 'D [de] MMM [de] YYYY',
        chartDate: 'D [de] MMM',
        monthShort: 'MMM',
        dayOfMonth: 'D',
        dayName: 'dddd, D [de] MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'zh-cn': {
        fullDateTime: 'YYYY年M月D日 ddd HH:mm',
        shortDateTime: 'YYYY年M月D日 HH:mm',
        shortDate: 'YYYY年M月D日',
        chartDate: 'M月D日',
        monthShort: 'M月',
        dayOfMonth: 'D日',
        dayName: 'M月D日 dddd',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'pt-br': {
        fullDateTime: 'ddd, D [de] MMM [de] YYYY HH:mm',
        shortDateTime: 'D [de] MMM [de] YYYY HH:mm',
        shortDate: 'D [de] MMM [de] YYYY',
        chartDate: 'D [de] MMM',
        monthShort: 'MMM',
        dayOfMonth: 'D',
        dayName: 'dddd, D [de] MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'vi': {
        fullDateTime: 'ddd, [ngày] D [tháng] M [năm] YYYY HH:mm',
        shortDateTime: '[ngày] D [tháng] M [năm] YYYY HH:mm',
        shortDate: 'D [tháng] M, YYYY',
        chartDate: 'D [tháng] M',
        monthShort: '[Th]M',
        dayOfMonth: 'D',
        dayName: '[ngày] D [tháng] M',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'zh-hk': {
        fullDateTime: 'YYYY年M月D日 ddd HH:mm',
        shortDateTime: 'YYYY年M月D日 HH:mm',
        shortDate: 'YYYY年M月D日',
        chartDate: 'M月D日',
        monthShort: 'M月',
        dayOfMonth: 'D日',
        dayName: 'M月D日 dddd',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'tr': {
        fullDateTime: 'ddd, D MMM YYYY HH:mm',
        shortDateTime: 'D MMM YYYY HH:mm',
        shortDate: 'D MMM YYYY',
        chartDate: 'D MMM',
        monthShort: 'MMM',
        dayOfMonth: 'D',
        dayName: 'dddd, D MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
    'hu': {
        fullDateTime: 'YYYY. MMM D., ddd HH:mm',
        shortDateTime: 'YYYY. MMM D. HH:mm',
        shortDate: 'YYYY. MMM D.',
        chartDate: 'MMM D.',
        monthShort: 'MMM',
        dayOfMonth: 'D.',
        dayName: 'dddd, MMMM D.',
        timeOnly: 'HH:mm',
        timezone: 'z'
    },
};

