import {useRef, useState} from "react";
import {t} from "@lingui/macro";
import {imageClient} from "../api/image.client.ts";
import {IdParam, ImageType} from "../types.ts";
import {showError} from "../utilites/notifications.tsx";

interface ImageUploadsOptions {
    entityId: IdParam;
    type: ImageType;
    room: number;
    onLimitReached?: () => void;
    onUploaded: (ids: number[]) => unknown;
}

export const useImageUploads = ({entityId, type, room, onLimitReached, onUploaded}: ImageUploadsOptions) => {
    const [uploading, setUploading] = useState(0);
    const inFlight = useRef(0);

    const track = (delta: number) => {
        inFlight.current += delta;
        setUploading(inFlight.current);
    };

    const upload = async (files: File[]) => {
        const accepted = files.slice(0, Math.max(0, room - inFlight.current));

        if (accepted.length < files.length) {
            onLimitReached?.();
        }

        if (accepted.length === 0) {
            return;
        }

        track(accepted.length);
        const ids: number[] = [];
        for (const file of accepted) {
            const fileName = file.name;
            try {
                const {data} = await imageClient.uploadImage(file, type, entityId);
                ids.push(Number(data.id));
            } catch (error: any) {
                showError(error?.response?.data?.errors?.image?.[0] ?? t`Could not upload ${fileName}`);
            }
        }

        try {
            if (ids.length > 0) {
                await onUploaded(ids);
            }
        } finally {
            track(-accepted.length);
        }
    };

    return {uploading, upload};
};
