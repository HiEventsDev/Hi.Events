import {t} from "@lingui/macro";
import {Button} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {Modal} from "../../common/Modal";
import {CashlessSalesPointForm} from "../../forms/CashlessSalesPointForm";
import {CashlessSalesPoint, GenericModalProps, UpsertCashlessSalesPointRequest} from "../../../types.ts";
import {useUpdateCashlessSalesPoint} from "../../../mutations/useUpdateCashlessSalesPoint.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {showSuccess} from "../../../utilites/notifications.tsx";

interface EditCashlessSalesPointModalProps extends GenericModalProps {
    salesPoint: CashlessSalesPoint;
}

export const EditCashlessSalesPointModal = ({salesPoint, onClose}: EditCashlessSalesPointModalProps) => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const updateMutation = useUpdateCashlessSalesPoint();
    const errorHandler = useFormErrorResponseHandler();

    const form = useForm<UpsertCashlessSalesPointRequest>({
        initialValues: {
            name: salesPoint.name,
            description: salesPoint.description ?? '',
            product_ids: (salesPoint.products ?? []).map((product) => String(product.id)) as unknown as number[],
            allow_staff_topups: salesPoint.allow_staff_topups,
            access_pin: '',
            activates_at: salesPoint.activates_at ?? '',
            expires_at: salesPoint.expires_at ?? '',
        },
    });

    const handleSubmit = form.onSubmit((values) => {
        updateMutation.mutate({
            eventId,
            salesPointId: salesPoint.id,
            salesPointData: {
                ...values,
                product_ids: values.product_ids.map(Number),
                access_pin: values.access_pin || null,
                activates_at: values.activates_at || null,
                expires_at: values.expires_at || null,
            },
        }, {
            onSuccess: () => {
                showSuccess(t`Sales point updated`);
                onClose();
            },
            onError: (error) => errorHandler(form, error),
        });
    });

    return (
        <Modal opened onClose={onClose} heading={t`Edit sales point`}>
            <form onSubmit={handleSubmit}>
                <CashlessSalesPointForm
                    form={form}
                    productCategories={event?.product_categories ?? []}
                    pinHelpText={salesPoint.has_access_pin
                        ? t`Leave blank to keep the current PIN.`
                        : t`Staff enter this PIN once when they open the till on their device.`}
                />

                <Button
                    type="submit"
                    fullWidth
                    mt="md"
                    loading={updateMutation.isPending}
                    data-testid="cashless-sales-point-submit-button"
                >
                    {t`Save sales point`}
                </Button>
            </form>
        </Modal>
    );
};
