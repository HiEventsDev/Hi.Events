import {UseFormReturnType} from "@mantine/form";
import {showError} from "../utilites/notifications.tsx";
import {t} from "@lingui/macro";

export const useFormErrorResponseHandler = () => {
    return (form: UseFormReturnType<any>, error: any, errorMessage = t`Please check the provided information is correct`) => {
        if (error?.response?.data?.errors) {
            form.setErrors(error.response.data.errors);
        }
        showError(errorMessage);
    }
}