import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {Button, Group} from "@mantine/core";
import {useForm} from "@mantine/form";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {useParams} from "react-router";
import {useUpdateAffiliate} from "../../../mutations/useUpdateAffiliate.ts";
import {useGetAffiliate} from "../../../queries/useGetAffiliate.ts";
import {UpdateAffiliateRequest} from "../../../api/affiliate.client.ts";
import {IdParam} from "../../../types.ts";
import {useEffect} from "react";
import {LoadingMask} from "../../common/LoadingMask";
import {AffiliateForm} from "../../forms/AffiliateForm";

interface EditAffiliateModalProps {
    affiliateId: IdParam;
    onClose: () => void;
}

export const EditAffiliateModal = ({affiliateId, onClose}: EditAffiliateModalProps) => {
    const {eventId} = useParams();
    const updateMutation = useUpdateAffiliate();
    const {data: affiliateData, isLoading} = useGetAffiliate(eventId!, affiliateId);
    const affiliate = affiliateData?.data;

    const form = useForm<UpdateAffiliateRequest>({
        initialValues: {
            name: '',
            email: '',
            status: 'ACTIVE'
        },
        validate: {
            name: (value) => !value ? t`Name is required` : null,
            email: (value) => {
                if (value && !/^\S+@\S+\.\S+$/.test(value)) {
                    return t`Invalid email format`;
                }
                return null;
            }
        },
    });

    useEffect(() => {
        if (affiliate) {
            form.setValues({
                name: affiliate.name,
                email: affiliate.email || '',
                status: affiliate.status
            });
        }
    }, [affiliate]);

    const handleSubmit = form.onSubmit((values) => {
        updateMutation.mutate({
            eventId: eventId!,
            affiliateId,
            affiliateData: {
                ...values,
                email: values.email || undefined,
            }
        }, {
            onSuccess: () => {
                showSuccess(t`Affiliate updated successfully`);
                onClose();
            },
            onError: (error: any) => {
                if (error.response?.data?.message) {
                    showError(error.response.data.message);
                } else {
                    showError(t`Failed to update affiliate`);
                }
            }
        });
    });

    if (isLoading) {
        return (
            <Modal
                opened={true}
                onClose={onClose}
                title={t`Edit Affiliate`}
                size="md"
            >
                <LoadingMask />
            </Modal>
        );
    }

    return (
        <Modal
            opened={true}
            onClose={onClose}
            heading={t`Edit Affiliate`}
            size="md"
        >
            <form onSubmit={handleSubmit}>
                <AffiliateForm
                    form={form}
                    isEditing={true}
                    existingCode={affiliate?.code}
                />

                <Button
                    type="submit"
                    loading={updateMutation.isPending}
                    fullWidth
                >
                    {t`Update Affiliate`}
                </Button>
            </form>
        </Modal>
    );
};
