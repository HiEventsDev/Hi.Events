import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {Button} from "@mantine/core";
import {useForm} from "@mantine/form";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {useParams} from "react-router";
import {useCreateAffiliate} from "../../../mutations/useCreateAffiliate.ts";
import {CreateAffiliateRequest} from "../../../api/affiliate.client.ts";
import {AffiliateForm} from "../../forms/AffiliateForm";

interface CreateAffiliateModalProps {
    onClose: () => void;
}

export const CreateAffiliateModal = ({onClose}: CreateAffiliateModalProps) => {
    const {eventId} = useParams();
    const createMutation = useCreateAffiliate();

    const form = useForm<CreateAffiliateRequest>({
        initialValues: {
            name: '',
            code: '',
            email: '',
            status: 'ACTIVE'
        },
        validateInputOnBlur: true,
        validate: {
            name: (value) => !value ? t`Name is required` : null,
            code: (value) => {
                if (!value) return t`Code is required`;
                if (!/^[a-zA-Z0-9_-]+$/.test(value)) return t`Code can only contain letters, numbers, hyphens, and underscores`;
                if (value.length < 3) return t`Code must be at least 3 characters`;
                if (value.length > 20) return t`Code must be no more than 20 characters`;
                return null;
            },
            email: (value) => {
                if (value && !/^\S+@\S+\.\S+$/.test(value)) {
                    return t`Invalid email format`;
                }
                return null;
            }
        },
    });

    const handleSubmit = form.onSubmit((values) => {
        createMutation.mutate({
            eventId: eventId!,
            affiliateData: {
                ...values,
                code: values.code.toUpperCase(),
                email: values.email || undefined,
            }
        }, {
            onSuccess: () => {
                showSuccess(t`Affiliate created successfully`);
                onClose();
            },
            onError: (error: any) => {
                if (error.response?.data?.message) {
                    showError(error.response.data.message);
                } else {
                    showError(t`Failed to create affiliate`);
                }
            }
        });
    });

    const generateRandomCode = () => {
        const randomCode = Math.random().toString(36).substring(2, 10).toUpperCase();
        form.setFieldValue('code', randomCode);
    };

    return (
        <Modal
            opened={true}
            onClose={onClose}
            heading={t`Create Affiliate`}
            size="md"
        >
            <form onSubmit={handleSubmit}>
                <AffiliateForm
                    form={form}
                    isEditing={false}
                    onGenerateCode={generateRandomCode}
                />

                <Button
                    type="submit"
                    loading={createMutation.isPending}
                    fullWidth
                    mt="md"
                >
                    {t`Create Affiliate`}
                </Button>
            </form>
        </Modal>
    );
};
