import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {imageClient} from "../api/image.client.ts";
import {GET_ACCOUNT_IMAGES_QUERY_KEY} from "../queries/useGetAccountImages.ts";

interface DeleteAccountImageVariables {
    imageId: IdParam;
    confirm?: boolean;
}

export const useDeleteAccountImage = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({imageId, confirm}: DeleteAccountImageVariables) =>
            imageClient.delete(imageId, {confirm}),

        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: [GET_ACCOUNT_IMAGES_QUERY_KEY],
            });
        },
    });
};
