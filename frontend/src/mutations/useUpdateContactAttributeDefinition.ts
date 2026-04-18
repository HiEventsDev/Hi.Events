import {useMutation, useQueryClient} from "@tanstack/react-query";
import {ContactAttributeDefinition, IdParam} from "../types.ts";
import {contactAttributeDefinitionClient} from "../api/contact-attribute-definition.client.ts";
import {GET_CONTACT_ATTRIBUTE_DEFINITIONS_QUERY_KEY} from "../queries/useGetContactAttributeDefinitions.ts";
import {useGetMe} from "../queries/useGetMe.ts";

export const useUpdateContactAttributeDefinition = () => {
    const queryClient = useQueryClient();
    const {data: me} = useGetMe();

    return useMutation({
        mutationFn: ({definitionId, definitionData}: {
            definitionId: IdParam,
            definitionData: Partial<ContactAttributeDefinition>,
        }) => contactAttributeDefinitionClient.update(me?.account_id, definitionId, definitionData),

        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_CONTACT_ATTRIBUTE_DEFINITIONS_QUERY_KEY]}),
    });
};
