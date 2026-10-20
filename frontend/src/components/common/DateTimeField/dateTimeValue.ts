export const joinDateAndTime = (date: string, time: string, defaultTime: string): string =>
    date ? `${date}T${time || defaultTime}` : '';
