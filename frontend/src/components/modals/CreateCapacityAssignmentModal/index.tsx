import {CapacityAssignmentRequest, GenericModalProps, ProductCategory} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {CapacityAssigmentForm} from "../../forms/CapaciyAssigmentForm";
import {useForm} from "@mantine/form";
import {Button} from "@mantine/core";
import {useCreateCapacityAssignment} from "../../../mutations/useCreateCapacityAssignment.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {useParams} from "react-router";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {NoResultsSplash} from "../../common/NoResultsSplash";
import {IconPlus} from "@tabler/icons-react";

export const CreateCapacityAssignmentModal = ({onClose}: GenericModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const {data: event} = useGetEvent(eventId);
    const form = useForm<CapacityAssignmentRequest>({
        initialValues: {
            name: '',
            capacity: undefined,
            status: 'ACTIVE',
            product_ids: [],
        }
    });
    const createMutation = useCreateCapacityAssignment();
    const eventHasProducts = event?.product_categories?.every(category => category.products?.length === 0) === false;

    const handleSubmit = (requestData: CapacityAssignmentRequest) => {
        createMutation.mutate({
            eventId: eventId,
            capacityAssignmentData: requestData,
        }, {
            onSuccess: () => {
                showSuccess(t`Capacity Assignment created successfully`);
                onClose();
            },
            onError: (error) => errorHandler(form, error),
        })
    }

    const NoProducts = () => {
        return (
            <NoResultsSplash
                imageHref={'/blank-slate/tickets.svg'}
                heading={t`Please create a product`}
                subHeading={(
                    <>
                        <p>
                            {t`You'll need at a product before you can create a capacity assignment.`}
                        </p>
                        <Button
                            size={'xs'}
                            leftSection={<IconPlus/>}
                            color={'green'}
                            onClick={() => window.location.href = `/manage/event/${eventId}/products/#create-product`}
                        >
                            {t`Create a Product`}
                        </Button>
                    </>
                )}
            />
        );
    }

    return (
        <Modal opened onClose={onClose} heading={eventHasProducts ? t`Create Capacity Assignment` : null}>
            {!eventHasProducts && <NoProducts/>}
            {eventHasProducts && (
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    {event && <CapacityAssigmentForm form={form}
                                                     productsCategories={event.product_categories as ProductCategory[]}/>}
                    <Button
                        type={'submit'}
                        fullWidth
                        loading={createMutation.isPending}
                    >
                        {t`Create Capacity Assignment`}
                    </Button>
                </form>
            )}
        </Modal>
    );
}
