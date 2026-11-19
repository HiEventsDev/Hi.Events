import {IdParam} from "../../../types.ts";
import {useGetCrmSmsConnection} from "../queries/useGetCrmSmsConnection.ts";
import {useTextMessagesVisible} from "./useTextMessagesVisible.ts";

export const useTextMessageSettingsVisible = (organizerId: IdParam | undefined): boolean => {
    const isAvailable = useTextMessagesVisible();
    const {data: connection} = useGetCrmSmsConnection(organizerId, !isAvailable);

    return isAvailable || !!connection;
};
