import {QueryClient, useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {downloadBinary} from "../../../utilites/download.ts";
import {crmClient} from "../api/crm.client.ts";
import {audienceMembershipKeys, crmKeys, invalidateCrm} from "../queries/crmQueryKeys.ts";
import {CrmBulkSelection} from "../types.ts";

export type CrmBulkRequest =
    | {action: 'ADD_TAGS' | 'REMOVE_TAGS'; tagIds: number[]}
    | {action: 'UNSUBSCRIBE' | 'DELETE'; confirmationCount: number};

export const invalidateAfterBulkChange = (queryClient: QueryClient, organizerId: IdParam) => invalidateCrm(queryClient, [
    ...audienceMembershipKeys(organizerId),
    crmKeys.contacts(organizerId),
    crmKeys.tags(organizerId),
]);

export const useStartCrmBulkOperation = () => {
    return useMutation({
        mutationFn: ({organizerId, selection, request}: {organizerId: IdParam, selection: CrmBulkSelection, request: CrmBulkRequest}) => {
            switch (request.action) {
                case 'ADD_TAGS':
                case 'REMOVE_TAGS':
                    return crmClient.bulkTagContacts(organizerId, selection, request.action, request.tagIds);
                case 'UNSUBSCRIBE':
                    return crmClient.bulkUnsubscribeContacts(organizerId, selection, request.confirmationCount);
                case 'DELETE':
                    return crmClient.bulkDeleteContacts(organizerId, selection, request.confirmationCount);
            }
        },
    });
};

export const useUndoCrmBulkOperation = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, operationId}: {organizerId: IdParam, operationId: IdParam}) => crmClient.undoBulkOperation(organizerId, operationId),
        onSuccess: (_, variables) => invalidateAfterBulkChange(queryClient, variables.organizerId),
    });
};

export const useExportSelectedCrmContacts = () => {
    return useMutation({
        mutationFn: ({organizerId, contactIds}: {organizerId: IdParam, contactIds: number[]}) => crmClient.exportSelectedContacts(organizerId, contactIds),
        onSuccess: blob => downloadBinary(blob, 'contacts.csv'),
    });
};
