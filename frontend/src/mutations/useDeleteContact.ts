import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {contactClient} from "../api/contact.client.ts";
import {GET_CONTACTS_QUERY_KEY} from "../queries/useGetContacts.ts";
import {useGetMe} from "../queries/useGetMe.ts";

export const useDeleteContact = () => {
    const queryClient = useQueryClient();
    const {data: me} = useGetMe();

    return useMutation({
        mutationFn: ({contactId}: {
            contactId: IdParam,
        }) => contactClient.delete(me?.account_id, contactId),

        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_CONTACTS_QUERY_KEY]}),
    });
};
