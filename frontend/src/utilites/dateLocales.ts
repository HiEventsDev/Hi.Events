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
import 'dayjs/locale/el';

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
    dateTimePicker: string;
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
        timezone: 'z',
        dateTimePicker: 'MMM DD, YYYY [at] h:mm A'
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
        timezone: 'z',
        dateTimePicker: 'MMM DD, YYYY [at] h:mm A'
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
        timezone: 'z',
        dateTimePicker: 'MMM DD, YYYY [at] h:mm A'
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
        timezone: 'z',
        dateTimePicker: 'MMM DD, YYYY [at] h:mm A'
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
        timezone: 'z',
        dateTimePicker: 'MMM DD, YYYY [at] h:mm A'
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
        timezone: 'z',
        dateTimePicker: 'MMM DD, YYYY [at] h:mm A'
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
        timezone: 'z',
        dateTimePicker: 'D [de] MMM [de] YYYY, HH:mm'
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
        timezone: 'z',
        dateTimePicker: 'MMM DD, YYYY [at] h:mm A'
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
        timezone: 'z',
        dateTimePicker: 'MMM DD, YYYY [at] h:mm A'
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
        timezone: 'z',
        dateTimePicker: 'MMM DD, YYYY [at] h:mm A'
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
        timezone: 'z',
        dateTimePicker: 'MMM DD, YYYY [at] h:mm A'
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
        timezone: 'z',
        dateTimePicker: 'MMM DD, YYYY [at] h:mm A'
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
        timezone: 'z',
        dateTimePicker: 'MMM DD, YYYY [at] h:mm A'
    },
    'pl': {
        fullDateTime: 'ddd, D MMM YYYY HH:mm',
        shortDateTime: 'D MMM YYYY HH:mm',
        shortDate: 'D MMM YYYY',
        chartDate: 'D MMM',
        monthShort: 'MMM',
        dayOfMonth: 'D',
        dayName: 'dddd, D MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z',
        dateTimePicker: 'MMM DD, YYYY [at] h:mm A'
    },
    'se': {
        fullDateTime: 'ddd D MMM YYYY HH:mm',
        shortDateTime: 'D MMM YYYY HH:mm',
        shortDate: 'D MMM YYYY',
        chartDate: 'D MMM',
        monthShort: 'MMM',
        dayOfMonth: 'D',
        dayName: 'dddd D MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z',
        dateTimePicker: 'MMM DD, YYYY [at] h:mm A'
    },
    'el': {
        fullDateTime: 'ddd, D MMM YYYY HH:mm',
        shortDateTime: 'D MMM YYYY HH:mm',
        shortDate: 'D MMM YYYY',
        chartDate: 'D MMM',
        monthShort: 'MMM',
        dayOfMonth: 'D',
        dayName: 'dddd, D MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z',
        dateTimePicker: 'D MMM YYYY HH:mm'
    },
};

