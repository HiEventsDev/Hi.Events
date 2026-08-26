import { SupportedLocales } from '../locales.ts';

export const localeFormats: Record<SupportedLocales, {
    fullDateTime: string;
    shortDateTime: string;
    shortDate: string;
    chartDate: string;
    dayMonthTime: string;
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
        dayMonthTime: 'MMM D, h:mm A',
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
        dayMonthTime: 'D. MMM HH:mm',
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
        dayMonthTime: 'D MMM HH:mm',
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
        dayMonthTime: 'D MMM HH:mm',
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
        dayMonthTime: 'D MMM HH:mm',
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
        dayMonthTime: 'D [de] MMM HH:mm',
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
        dayMonthTime: 'D [de] MMM HH:mm',
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
        dayMonthTime: 'M月D日 HH:mm',
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
        dayMonthTime: 'D [de] MMM HH:mm',
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
        dayMonthTime: 'D [tháng] M HH:mm',
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
        dayMonthTime: 'M月D日 HH:mm',
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
        dayMonthTime: 'D MMM HH:mm',
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
        dayMonthTime: 'MMM D. HH:mm',
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
        dayMonthTime: 'D MMM HH:mm',
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
        dayMonthTime: 'D MMM HH:mm',
        monthShort: 'MMM',
        dayOfMonth: 'D',
        dayName: 'dddd D MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z',
        dateTimePicker: 'MMM DD, YYYY [at] h:mm A'
    },
    'sk': {
        fullDateTime: 'ddd D. MMM YYYY HH:mm',
        shortDateTime: 'D. MMM YYYY HH:mm',
        shortDate: 'D. MMM YYYY',
        chartDate: 'D. MMM',
        dayMonthTime: 'D. MMM HH:mm',
        monthShort: 'MMM',
        dayOfMonth: 'D.',
        dayName: 'dddd D. MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z',
        dateTimePicker: 'D. MMM YYYY HH:mm'
    },
    'el': {
        fullDateTime: 'ddd, D MMM YYYY HH:mm',
        shortDateTime: 'D MMM YYYY HH:mm',
        shortDate: 'D MMM YYYY',
        chartDate: 'D MMM',
        dayMonthTime: 'D MMM HH:mm',
        monthShort: 'MMM',
        dayOfMonth: 'D',
        dayName: 'dddd, D MMMM',
        timeOnly: 'HH:mm',
        timezone: 'z',
        dateTimePicker: 'D MMM YYYY HH:mm'
    },
};
