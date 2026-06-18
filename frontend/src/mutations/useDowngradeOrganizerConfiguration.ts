import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {GET_ORGANIZER_QUERY_KEY} from "../queries/useGetOrganizer.ts";
import {GET_ORGANIZER_SETTINGS_QUERY_KEY} from "../queries/useGetOrganizerSettings.ts";
import {GET_PLATFORM_FEE_PREVIEW_QUERY_KEY} from "../queries/useGetPlatformFeePreview.ts";
import {organizerClient} from "../api/organizer.client.ts";

export const useDowngradeOrganizerConfiguration = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId}: { organizerId: IdParam }) =>
            organizerClient.downgradeConfiguration(organizerId),

        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({queryKey: [GET_ORGANIZER_QUERY_KEY, variables.organizerId]});
            queryClient.invalidateQueries({queryKey: [GET_ORGANIZER_SETTINGS_QUERY_KEY, variables.organizerId]});
            queryClient.invalidateQueries({queryKey: [GET_PLATFORM_FEE_PREVIEW_QUERY_KEY]});
        }
    });
}
