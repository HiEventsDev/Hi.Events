export const firstApiError = (error: unknown, fallback: string): string => {
    const data = (error as { response?: { data?: { errors?: Record<string, unknown>; message?: unknown } } })?.response?.data;
    const firstError = Object.values(data?.errors ?? {})
        .flat()
        .find((message): message is string => typeof message === 'string' && message !== '');
    if (firstError) return firstError;
    return typeof data?.message === 'string' && data.message !== '' ? data.message : fallback;
};
