import {useMutation} from "@tanstack/react-query";
import {imageClient} from "../api/image.client.ts";
import {IdParam, ImageType} from "../types.ts";

export const useUploadImage = () => {
    return useMutation({
        mutationFn: ({image, imageType = undefined, entityId = undefined}: {
            image: File,
            imageType?: ImageType
            entityId?: IdParam
        }) => imageClient.uploadImage(image, imageType, entityId),
    });
}
