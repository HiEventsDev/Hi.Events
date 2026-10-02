import {UseFormReturnType} from "@mantine/form";
import {showError} from "../utilites/notifications";
import {t} from "@lingui/macro";

type ErrorResponse = {
    response?: {
        data?: {
            errors?: Record<string, string>;
            message?: string;
        };
        status?: number;
    };
};

export const useFormErrorResponseHandler = () => {
    return (
        form: UseFormReturnType<any>,
        error: ErrorResponse | any,
        errorMessage = t`Please check the provided information is correct`
    ) => {
        const fieldErrors = error?.response?.data?.errors;
        const hasFieldErrors = !!fieldErrors && Object.keys(fieldErrors).length > 0;

        if (hasFieldErrors) {
            form.setErrors(fieldErrors);
        }

        if (error?.response?.status && error.response.status >= 500) {
            showError((
                <>
                    <p>
                        {t`There was an error processing your request. Please try again.`}
                    </p>
                    <p style={{fontSize: '0.8rem', color: '#ccc'}}>
                        Error: {error.response.status}
                    </p>
                    {error.response.data?.message && (
                        <p style={{fontSize: '0.8rem', color: '#ccc'}}>
                            {error.response.data.message}
                        </p>
                    )}
                </>
            ));
            return;
        }

        if (error?.response?.status && error.response.status >= 400) {
            const serverMessage = error.response.data?.message;
            showError(hasFieldErrors || !serverMessage ? errorMessage : serverMessage);
            return;
        }

        showError(t`An unexpected error occurred. Please try again.`);
    };
};
