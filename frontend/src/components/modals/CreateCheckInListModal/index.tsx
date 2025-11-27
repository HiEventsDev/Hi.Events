import {CheckInList, CheckInListRequest, GenericModalProps, Product, ProductCategory} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {CheckInListForm} from "../../forms/CheckInListForm";
import {useForm} from "@mantine/form";
import {Button} from "@mantine/core";
import {useCreateCheckInList} from "../../../mutations/useCreateCheckInList.ts";
import {useParams} from "react-router";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {NoResultsSplash} from "../../common/NoResultsSplash";
import {IconPlus} from "@tabler/icons-react";
import {CheckInListSuccessModal} from "../CheckInListSuccessModal";
import {useState} from "react";

export const CreateCheckInListModal = ({onClose}: GenericModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const {data: event} = useGetEvent(eventId);
    const [createdCheckInList, setCreatedCheckInList] = useState<CheckInList | null>(null);
    const form = useForm<CheckInListRequest>({
        initialValues: {
            name: '',
            description: '',
            expires_at: '',
            activates_at: '',
            product_ids: [],
        }
    });
    const createMutation = useCreateCheckInList();
    const eventHasTickets = event?.product_categories
        && event.product_categories.some(category => category.products.length > 0)
        && event.product_categories.filter(category =>
            category?.products?.filter((product: Product) => product.product_type === 'TICKET').length > 0).length > 0

    const handleSubmit = (requestData: CheckInListRequest) => {
        createMutation.mutate({
            eventId: eventId,
            checkInListData: requestData,
        }, {
            onSuccess: (response) => {
                setCreatedCheckInList(response.data);
            },
            onError: (error) => errorHandler(form, error),
        })
    }

    const NoProducts = () => {
        return (
            <NoResultsSplash
                imageHref={'/blank-slate/tickets.svg'}
                heading={t`Please create a ticket`}
                subHeading={(
                    <>
                        <p>
                            {t`You'll need a ticket before you can create a check-in list.`}
                        </p>
                        <Button
                            size={'xs'}
                            leftSection={<IconPlus/>}
                            color={'green'}
                            onClick={() => window.location.href = `/manage/event/${eventId}/products/#create-product`}
                        >
                            {t`Create a Ticket`}
                        </Button>
                    </>
                )}
            />
        );
    }

    if (createdCheckInList) {
        return (
            <CheckInListSuccessModal
                onClose={onClose}
                checkInListName={createdCheckInList.name}
                checkInListShortId={createdCheckInList.short_id}
            />
        );
    }

    return (
        <Modal opened onClose={onClose} heading={eventHasTickets ? t`Create Check-In List` : null}>
            {!eventHasTickets && <NoProducts/>}
            {eventHasTickets && (
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    {event && (
                        <CheckInListForm
                            form={form}
                            productCategories={event.product_categories as ProductCategory[]}
                        />
                    )}
                    <Button
                        type={'submit'}
                        fullWidth
                        loading={createMutation.isPending}
                        mt="md"
                    >
                        {t`Create Check-In List`}
                    </Button>
                </form>
            )}
        </Modal>
    );
}
