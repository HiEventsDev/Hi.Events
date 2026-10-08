export const WIDGET_COLOR_PARAMS = [
    'BackgroundColor',
    'PrimaryColor',
    'PrimaryTextColor',
    'SecondaryColor',
    'SecondaryTextColor',
] as const;

export const widgetColorsFromSearchParams = (searchParams: URLSearchParams) => ({
    background: searchParams.get("BackgroundColor") || '#ffffff',
    primary: searchParams.get("PrimaryColor") || '#7b5db8',
    primaryText: searchParams.get("PrimaryTextColor") || '#000000',
    secondary: searchParams.get("SecondaryColor") || '#7b5eb9',
    secondaryText: searchParams.get("SecondaryTextColor") || '#ffffff',
    bodyBackground: searchParams.get("BackgroundColor") || '#ffffff',
});
