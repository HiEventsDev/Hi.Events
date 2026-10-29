const IMAGEKIT_URL = /^(https:\/\/ik\.imagekit\.io\/[^/]+\/tr:)([^/]+)(\/.+)$/;

const RESPONSIVE_WIDTHS = [480, 960, 1400];

const withWidth = (url: string, width: number): string | null => {
    const match = url.match(IMAGEKIT_URL);
    if (!match) {
        return null;
    }
    const [, prefix, transform, path] = match;
    const params = transform.split(',').filter(param => !param.startsWith('w-'));

    return `${prefix}${[`w-${width}`, ...params].join(',')}${path}`;
};

export const sizedImageUrl = (url: string, width: number): string => withWidth(url, width) ?? url;

export const responsiveImage = (url: string, sizes: string): {src: string, srcSet?: string, sizes?: string} => {
    if (!IMAGEKIT_URL.test(url)) {
        return {src: url};
    }

    return {
        src: url,
        srcSet: RESPONSIVE_WIDTHS.map(width => `${withWidth(url, width)} ${width}w`).join(', '),
        sizes,
    };
};
