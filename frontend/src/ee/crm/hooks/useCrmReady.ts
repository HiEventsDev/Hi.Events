import {IdParam} from "../../../types.ts";
import {useGetCrmSyncStatus} from "../queries/useGetCrmSyncStatus.ts";
import {CrmSyncStatus} from "../types.ts";
import {useCrmAccess} from "./useCrmAccess.ts";

export const isAudienceBuilt = (status: CrmSyncStatus | undefined): boolean =>
    status?.status === 'READY' || !!status?.synced_at;

export const useCrmReady = (organizerId: IdParam | undefined): boolean => {
    const {isVisible} = useCrmAccess();
    const {data} = useGetCrmSyncStatus(organizerId, isVisible);

    return isVisible && isAudienceBuilt(data);
};
