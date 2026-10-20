import {t} from "@lingui/macro";

export const IMAGE_MAX_UPLOAD_SIZE = 5 * 1024 * 1024;

export const validateImageFile = (file: File): string | null => {
    if (file.size > IMAGE_MAX_UPLOAD_SIZE) {
        return t`File is too large. Maximum size is 5MB.`;
    }
    if (!file.type.startsWith("image/")) {
        return t`Invalid file type. Please upload an image.`;
    }
    return null;
};

export const extractImageUploadErrors = (error: any): string[] => {
    if (error?.response?.data?.errors?.image) {
        return error.response.data.errors.image;
    }
    if (error?.response?.status === 413) {
        return [t`File is too large. Maximum size is 5MB.`];
    }
    if (typeof error?.response?.data?.message === "string" && error.response.data.message) {
        return [error.response.data.message];
    }
    if (!error?.response) {
        return [t`The upload didn't reach the server. Check your connection, and if the file is large, try a smaller image (max 5MB).`];
    }
    return [t`Failed to upload image. Please try again.`];
};
