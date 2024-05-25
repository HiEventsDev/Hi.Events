import {t} from "@lingui/macro";
import {AxiosError} from "axios";
import {useEffect} from "react";
import {showError} from "../../../utilites/notifications.tsx";

interface ErrorLoadingMessageProps {
    error?: AxiosError;
}

export const ErrorLoadingMessage = ({error}: ErrorLoadingMessageProps) => {
    useEffect(() => {
        if (error) {
            showError(error.message || t`We couldn't load the data. Please try again.`);
        }
    }, []);


    return (
        <></>
    );
}