import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {imageClient} from "../api/image.client.ts";

export const useDeleteImage = () => {
    return useMutation({
        mutationFn: ({imageId}: {
            imageId: IdParam,
        }) => imageClient.delete(imageId),
    });
}
