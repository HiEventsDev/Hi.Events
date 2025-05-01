import {useMutation} from "@tanstack/react-query";
import {imageClient} from "../api/image.client.ts";

export const useUploadImage = () => {
    return useMutation({
        mutationFn: ({image}: {
            image: File,
        }) => imageClient.uploadImage(image),
    });
}
