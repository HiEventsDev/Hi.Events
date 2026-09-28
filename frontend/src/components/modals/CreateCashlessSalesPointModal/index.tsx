import {t} from "@lingui/macro";
import {Button} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {Modal} from "../../common/Modal";
import {CashlessSalesPointForm} from "../../forms/CashlessSalesPointForm";
import {GenericModalProps, UpsertCashlessSalesPointRequest} from "../../../types.ts";
import {useCreateCashlessSalesPoint} from "../../../mutations/useCreateCashlessSalesPoint.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {showSuccess} from "../../../utilites/notifications.tsx";

export const CreateCashlessSalesPointModal = ({onClose}: GenericModalProps) => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const createMutation = useCreateCashlessSalesPoint();
    const errorHandler = useFormErrorResponseHandler();

    const form = useForm<UpsertCashlessSalesPointRequest>({
        initialValues: {
            name: '',
            description: '',
            product_ids: [],
            allow_staff_topups: true,
            access_pin: '',
            activates_at: '',
            expires_at: '',
        },
    });

    const handleSubmit = form.onSubmit((values) => {
        createMutation.mutate({
            eventId,
            salesPointData: {
                ...values,
                product_ids: values.product_ids.map(Number),
                access_pin: values.access_pin || null,
                activates_at: values.activates_at || null,
                expires_at: values.expires_at || null,
            },
        }, {
            onSuccess: () => {
                showSuccess(t`Sales point created`);
                onClose();
            },
            onError: (error) => errorHandler(form, error),
        });
    });

    return (
        <Modal opened onClose={onClose} heading={t`Create a sales point`}>
            <form onSubmit={handleSubmit}>
                <CashlessSalesPointForm
                    form={form}
                    productCategories={event?.product_categories ?? []}
                    pinHelpText={t`Staff enter this PIN once when they open the till on their device.`}
                />

                <Button
                    type="submit"
                    fullWidth
                    mt="md"
                    loading={createMutation.isPending}
                    data-testid="cashless-sales-point-submit-button"
                >
                    {t`Create sales point`}
                </Button>
            </form>
        </Modal>
    );
};
