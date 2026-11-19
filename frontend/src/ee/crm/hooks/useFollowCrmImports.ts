import {useEffect, useRef} from "react";
import {useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {audienceMembershipKeys, crmKeys, invalidateCrm} from "../queries/crmQueryKeys.ts";
import {useGetCrmImports} from "../queries/useGetCrmImports.ts";

export const useFollowCrmImports = (organizerId: IdParam | undefined, enabled: boolean) => {
    const queryClient = useQueryClient();
    const importsQuery = useGetCrmImports(organizerId, enabled);
    const isImporting = !!importsQuery.data?.some(entry => entry.status === 'PROCESSING');
    const wasImporting = useRef(false);

    useEffect(() => {
        if (isImporting) {
            wasImporting.current = true;
        } else if (wasImporting.current && organizerId) {
            wasImporting.current = false;
            void invalidateCrm(queryClient, [...audienceMembershipKeys(organizerId), crmKeys.contacts(organizerId), crmKeys.tags(organizerId)]);
        }
    }, [isImporting, organizerId, queryClient]);
};
