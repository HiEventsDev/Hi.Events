import {GenericModalProps, PromoCode, PromoCodeDiscountType} from "../../../types.ts";
import {useForm} from "@mantine/form";
import {useParams} from "react-router-dom";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.ts";
import {PromoCodeForm} from "../../forms/PromoCodeForm";
import {Modal} from "../../common/Modal";
import {Button} from "../../common/Button";
import {useCreatePromoCode} from "../../../mutations/useCreatePromoCode.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";

export const CreatePromoCodeModal = ({onClose}: GenericModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const mutation = useCreatePromoCode();
    const form = useForm<PromoCode>({
        initialValues: {
            code: '',
            discount: undefined,
            applicable_ticket_ids: [],
            expiry_date: undefined,
            discount_type: PromoCodeDiscountType.None,
            max_allowed_usages: undefined,
        },
    });


    const handleSubmit = (values: PromoCode) => {
        mutation.mutate({
            eventId: eventId,
            promoCodeData: {
                ...values,
            },
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Created Promo Code`);
                form.reset();
                onClose();
            },
            onError: (error) => errorHandler(form, error),
        })
    }

    return (
        <Modal
            opened
            onClose={onClose}
            heading={t`Create Promo Code`}
        >
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <PromoCodeForm form={form}/>
                <Button type="submit" fullWidth mt="xl" disabled={mutation.isLoading}>
                    {mutation.isLoading ? t`Working...` : t`Create Promo Code`}
                </Button>
            </form>
        </Modal>
    )
};