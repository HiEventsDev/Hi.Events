import {useMutation} from "@tanstack/react-query";
import {IdParam, QueryFilters} from "../../../types.ts";
import {downloadBinary} from "../../../utilites/download.ts";
import {crmClient} from "../api/crm.client.ts";
import {CrmSegmentReference} from "../types.ts";

export const useExportCrmContacts = () => {
    return useMutation({
        mutationFn: ({organizerId, pagination, segment}: {
            organizerId: IdParam,
            pagination: QueryFilters,
            segment?: CrmSegmentReference,
        }) => crmClient.exportContacts(organizerId, pagination, segment),

        onSuccess: blob => downloadBinary(blob, 'contacts.csv'),
    });
};
