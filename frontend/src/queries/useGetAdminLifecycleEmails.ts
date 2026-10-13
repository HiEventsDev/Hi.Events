import {useQuery} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";

export const GET_ADMIN_LIFECYCLE_EMAILS_QUERY_KEY = ['admin', 'lifecycle-emails'];

export const useGetAdminLifecycleEmails = (enabled = true) => {
    return useQuery({
        queryKey: GET_ADMIN_LIFECYCLE_EMAILS_QUERY_KEY,
        queryFn: () => adminClient.getLifecycleEmails(),
        enabled,
        retry: false,
    });
};
