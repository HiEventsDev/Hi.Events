import {GenericModalProps, PromoCode} from "../../../types.ts";
import {hasLength, useForm} from "@mantine/form";
import {useParams} from "react-router-dom";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {PromoCodeForm} from "../../forms/PromoCodeForm";
import {Modal} from "../../common/Modal";
import {Button} from "../../common/Button";
import {useGetPromoCode} from "../../../queries/useGetPromoCode.ts";
import {useEffect} from "react";
import {useUpdatePromoCode} from "../../../mutations/useUpdatePromoCode.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {LoadingMask} from "../../common/LoadingMask";
import {utcToTz} from "../../../utilites/dates.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";

interface EditPromoCodeModalProps {
    promoCodeId: number;
}

export const EditPromoCodeModal = ({onClose, promoCodeId}: EditPromoCodeModalProps & GenericModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const promoCodeQuery = useGetPromoCode(eventId, promoCodeId);
    const {data: event} = useGetEvent(eventId);
    const form = useForm<PromoCode>({
        initialValues: {
            code: '',
            discount: undefined,
            applicable_ticket_ids: [],
            expiry_date: undefined,
            discount_type: undefined,
            max_allowed_usages: undefined,
        },
        validate: {
            code: hasLength({min: 3, max: 50}, t`Code must be between 3 and 50 characters long`),
        },
        validateInputOnBlur: true,
    });

    const mutation = useUpdatePromoCode();

    const handleSubmit = (values: PromoCode) => {
        mutation.mutate({
            promoCodeId: promoCodeId,
            eventId: eventId,
            promoCodeData: values,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Promo Code`);
                form.reset();
                onClose();
            },
            onError: (error) => errorHandler(form, error),
        })
    }

    useEffect(() => {
        const {data} = promoCodeQuery;
        if (!data || !event) {
            return;
        }

        form.setValues({
            code: data.code,
            discount: data.discount,
            applicable_ticket_ids: data.applicable_ticket_ids,
            expiry_date: utcToTz(data.expiry_date, event.timezone),
            discount_type: data.discount_type,
            max_allowed_usages: data.max_allowed_usages || undefined,
        });

    }, [promoCodeQuery.isFetched, event]);

    return (
        <Modal
            opened
            onClose={onClose}
            heading={t`Edit Promo Code`}
        >
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <PromoCodeForm form={form}/>
                <Button type="submit" fullWidth mt="xl" disabled={mutation.isLoading}>
                    {mutation.isLoading ? t`Working...` : t`Edit Promo Code`}
                </Button>
            </form>
            <LoadingMask/>
        </Modal>
    )
};
