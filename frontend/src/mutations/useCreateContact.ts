import {useMutation, useQueryClient} from "@tanstack/react-query";
import {Contact} from "../types.ts";
import {contactClient} from "../api/contact.client.ts";
import {GET_CONTACTS_QUERY_KEY} from "../queries/useGetContacts.ts";
import {useGetMe} from "../queries/useGetMe.ts";

export const useCreateContact = () => {
    const queryClient = useQueryClient();
    const {data: me} = useGetMe();

    return useMutation({
        mutationFn: ({contactData}: {
            contactData: Partial<Contact>
        }) => contactClient.create(me?.account_id, contactData),

        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_CONTACTS_QUERY_KEY]}),
    });
};
