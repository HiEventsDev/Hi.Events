import {IdParam} from "../../../types.ts";
import {useGetCrmSmsConnection} from "../queries/useGetCrmSmsConnection.ts";
import {useTextMessagesVisible} from "./useTextMessagesVisible.ts";

interface TextMessageSettingsVisibility {
    isVisible: boolean;
    isResolved: boolean;
}

export const useTextMessageSettingsVisible = (organizerId: IdParam | undefined): TextMessageSettingsVisibility => {
    const isAvailable = useTextMessagesVisible();
    const {data: connection, isLoading} = useGetCrmSmsConnection(organizerId, !isAvailable);

    return {isVisible: isAvailable || !!connection, isResolved: isAvailable || !isLoading};
};
