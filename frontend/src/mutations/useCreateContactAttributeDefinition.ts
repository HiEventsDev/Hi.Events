import {useMutation, useQueryClient} from "@tanstack/react-query";
import {ContactAttributeDefinition} from "../types.ts";
import {contactAttributeDefinitionClient} from "../api/contact-attribute-definition.client.ts";
import {GET_CONTACT_ATTRIBUTE_DEFINITIONS_QUERY_KEY} from "../queries/useGetContactAttributeDefinitions.ts";
import {useGetMe} from "../queries/useGetMe.ts";

export const useCreateContactAttributeDefinition = () => {
    const queryClient = useQueryClient();
    const {data: me} = useGetMe();

    return useMutation({
        mutationFn: ({definitionData}: {
            definitionData: Partial<ContactAttributeDefinition>
        }) => contactAttributeDefinitionClient.create(me?.account_id, definitionData),

        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_CONTACT_ATTRIBUTE_DEFINITIONS_QUERY_KEY]}),
    });
};
