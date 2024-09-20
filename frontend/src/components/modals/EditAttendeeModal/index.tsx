import {Modal} from "../../common/Modal";
import {GenericModalProps} from "../../../types.ts";
import {Button} from "../../common/Button";
import {useParams} from "react-router-dom";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {useForm} from "@mantine/form";
import {LoadingOverlay, Select, TextInput} from "@mantine/core";
import {EditAttendeeRequest} from "../../../api/attendee.client.ts";
import {useGetAttendee} from "../../../queries/useGetAttendee.ts";
import {useEffect} from "react";
import {useUpdateAttendee} from "../../../mutations/useUpdateAttendee.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {IconInfoCircle} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {InputGroup} from "../../common/InputGroup";

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

                {event?.products && (
                    <Select
                        mt={20}
                        description={<><IconInfoCircle size={12}/> Changing an attendee's products will adjust product
                            quantities</>}
                        data={event.products.map(product => {
                            return {
                                value: String(product.id),
                                label: product.title,
                            };
                        })}
                        {...form.getInputProps('product_id')}
                        label={t`Product`}
                        required
                    />
                )}

                {event?.products?.find(product => product.id == form.values.product_id)?.type === 'TIERED' && (
                    <Select
                        label={t`Product Tier`}
                        mt={20}
                        placeholder={t`Select Product Tier`}
                        {...form.getInputProps('product_price_id')}
                        data={event?.products?.find(product => product.id == form.values.product_id)?.prices?.map(price => {
                            return {
                                value: String(price.id),
                                label: String(price.label),
                            };
                        })}
                    />
                )}

                <Button type="submit" fullWidth mt="xl" disabled={mutation.isPending}>
                    {mutation.isPending ? t`Working...` : t`Edit Attendee`}
                </Button>
            </form>
        </Modal>
    );
}
