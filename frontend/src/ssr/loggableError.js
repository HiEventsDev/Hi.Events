export const loggableError = (error) => {
    if (error?.isAxiosError !== true) {
        return error;
    }

    return {
        message: error.message,
        code: error.code,
        status: error.response?.status,
        method: error.config?.method,
        url: error.config?.url,
    };
};
