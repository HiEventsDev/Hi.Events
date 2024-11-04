import {Modal} from "../../common/Modal";
import {GenericModalProps, ProductCategory, ProductType} from "../../../types.ts";
import {Button} from "../../common/Button";
import {useParams} from "react-router-dom";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {useForm} from "@mantine/form";
import {LoadingOverlay, TextInput} from "@mantine/core";
import {EditAttendeeRequest} from "../../../api/attendee.client.ts";
import {useGetAttendee} from "../../../queries/useGetAttendee.ts";
import {useEffect} from "react";
import {useUpdateAttendee} from "../../../mutations/useUpdateAttendee.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {t} from "@lingui/macro";
import {InputGroup} from "../../common/InputGroup";
import {ProductSelector} from "../../common/ProductSelector";

interface EditAttendeeModalProps extends GenericModalProps {
    attendeeId: number;
}

export const EditAttendeeModal = ({onClose, attendeeId}: EditAttendeeModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const {data: attendee, isFetched} = useGetAttendee(eventId, attendeeId);
    const {data: event} = useGetEvent(eventId);
    const mutation = useUpdateAttendee();
    const form = useForm<EditAttendeeRequest>({
        initialValues: {
            first_name: '',
            last_name: '',
            email: '',
            product_id: '',
            product_price_id: '',
        },
    });

    useEffect(() => {
        if (!attendee) {
            return;
        }

        form.setValues({
            first_name: attendee.first_name,
            last_name: attendee.last_name,
            email: attendee.email,
            product_id: String(attendee.product_id),
            product_price_id: String(attendee.product_price_id),
        });

    }, [isFetched]);

    const handleSubmit = (values: EditAttendeeRequest) => {
        mutation.mutate({
            attendeeId: attendeeId,
            eventId: eventId,
            attendeeData: values,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully updated attendee`);
                onClose();
            },
            onError: (error) => errorHandler(form, error),
        })
    };

    if (!isFetched) {
        return <LoadingOverlay visible/>
    }

    return (
        <Modal opened onClose={onClose} heading={t`Edit Attendee`}>
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <InputGroup>
                    <TextInput
                        {...form.getInputProps('first_name')}
                        label={t`First name`}
                        placeholder={t`Homer`}
                        required
                    />

                    <TextInput
                        {...form.getInputProps('last_name')}
                        label={t`Last name`}
                        placeholder={t`Simpson`}
                        required
                    />
                </InputGroup>
                <TextInput
                    {...form.getInputProps('email')}
                    label={t`Email address`}
                    placeholder="homer@simpson.com"
                    required
                />

                {event?.product_categories && event.product_categories.length > 0 && (
                    <ProductSelector
                        placeholder={t`Select Product`}
                        label={t`Product`}
                        productCategories={event.product_categories as ProductCategory[]}
                        form={form}
                        productFieldName={'product_id'}
                        includedProductTypes={[ProductType.Ticket]}
                        multiSelect={false}
                        showTierSelector={true}
                    />
                )}

                <Button type="submit" fullWidth mt="xl" disabled={mutation.isPending}>
                    {mutation.isPending ? t`Working...` : t`Edit Attendee`}
                </Button>
            </form>
        </Modal>
    );
}
