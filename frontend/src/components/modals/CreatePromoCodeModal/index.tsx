import {GenericModalProps, PromoCode, PromoCodeDiscountType} from "../../../types.ts";
import {hasLength, useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
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
            applicable_product_ids: [],
            expiry_date: undefined,
            discount_type: PromoCodeDiscountType.None,
            max_allowed_usages: undefined,
        },
        validate: {
            code: hasLength({min: 3, max: 50}, t`Code must be between 3 and 50 characters long`),
        },
        validateInputOnBlur: true,
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
                <Button type="submit" fullWidth mt="xl" disabled={mutation.isPending}>
                    {mutation.isPending ? t`Working...` : t`Create Promo Code`}
                </Button>
            </form>
        </Modal>
    )
};
