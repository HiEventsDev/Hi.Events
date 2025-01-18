import {useForm} from "@mantine/form";
import {GenericModalProps, CreateApiKeyRequest, ApiKey} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {Button} from "@mantine/core";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {t, Trans} from "@lingui/macro";
import {useCreateApiKey} from "../../../mutations/useCreateApiKey.ts";
import {showSuccess, showError} from "../../../utilites/notifications.tsx";
import {ApiKeyForm} from "../../forms/ApiKeyForm/index.tsx";
import {apiKeysClient} from "../../../api/api-keys.client.ts";
import {useMutation, useQueryClient} from "@tanstack/react-query";
import {GET_API_KEYS_QUERY_KEY} from "../../../queries/useGetApiKeys.ts";

interface CreateApiKeyModalProps extends GenericModalProps {
    onCompleted: (apiKey: ApiKey) => void;
}

// TODO: translations
export const CreateApiKeyModal = ({onClose, onCompleted}: CreateApiKeyModalProps) => {
    const createMutation = useCreateApiKey();
    const queryClient = useQueryClient();
    const formErrorHandler = useFormErrorResponseHandler();

    const form = useForm<CreateApiKeyRequest>({
        initialValues: {
            token_name: '',
            abilities: [],
            expires_at: '',
        },
    });

    const handleCreate = useMutation({
        mutationFn: (apiKeyRequest: CreateApiKeyRequest) => apiKeysClient.create(apiKeyRequest),

        onSuccess: (response: NewApiKey) => {
            showSuccess(`Successfully Created API Key`);
            queryClient.invalidateQueries({queryKey: [GET_API_KEYS_QUERY_KEY]}).then(() => {
                onCompleted(response);
                onClose();
                form.reset();
            });
        },

        onError: (error: any) => {
            if (error?.response?.data?.errors) {
                form.setErrors(error.response.data.errors);
            } else {
                showError(`Unable to create API key. Please check the logs for details`);
            }
        }
    });

    return (
        <Modal heading={'Create API Key'} onClose={onClose} opened>
            <form onSubmit={form.onSubmit((values) => handleCreate.mutate(values as any as CreateApiKeyRequest))}>
                <ApiKeyForm form={form} />

                <Button
                    fullWidth
                    loading={createMutation.isPending}
                    type={'submit'}>
                    {'Create Key'}
                </Button>
            </form>
        </Modal>
    )
}
