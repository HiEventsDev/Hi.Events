import {IdParam} from "../../../types.ts";
import {useGetCrmSmsConnection} from "../queries/useGetCrmSmsConnection.ts";
import {useTextMessagesPaused, useTextMessagesVisible} from "./useTextMessagesVisible.ts";

export const useSmsConnectionMissing = (organizerId: IdParam | undefined): boolean => {
    const isVisible = useTextMessagesVisible();
    const isPaused = useTextMessagesPaused();
    const isAvailable = isVisible && !isPaused;
    const {data: connection, isSuccess} = useGetCrmSmsConnection(organizerId, isAvailable);

    return isAvailable && isSuccess && !connection;
};
