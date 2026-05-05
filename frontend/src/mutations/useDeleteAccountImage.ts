import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {imageClient} from "../api/image.client.ts";
import {GET_ACCOUNT_IMAGES_QUERY_KEY} from "../queries/useGetAccountImages.ts";

export const useDeleteAccountImage = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (imageId: IdParam) => imageClient.delete(imageId),

        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: [GET_ACCOUNT_IMAGES_QUERY_KEY],
            });
        },
    });
};
