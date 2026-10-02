import {BoxOfficeRequest, BoxOfficeWithPin, EventType, GenericModalProps, ProductCategory} from "../../../../../types.ts";
import {useState} from "react";
import {BoxOfficePinModal} from "../BoxOfficePinModal";
import {Modal} from "../../../../../components/common/Modal";
import {t} from "@lingui/macro";
import {BoxOfficeForm} from "../../forms/BoxOfficeForm";
import {useForm} from "@mantine/form";
import {Button} from "@mantine/core";
import {useCreateBoxOffice} from "../../../mutations/useCreateBoxOffice.ts";
import {useParams} from "react-router";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {useGetEventCheckInLists} from "../../../../../queries/useGetCheckInLists.ts";
import {NoResultsSplash} from "../../../../../components/common/NoResultsSplash";
import {IconPlus} from "@tabler/icons-react";

export const CreateBoxOfficeModal = ({onClose}: GenericModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const {data: event} = useGetEvent(eventId);
    const {data: checkInLists} = useGetEventCheckInLists(eventId, {perPage: 100});
    const [created, setCreated] = useState<BoxOfficeWithPin | null>(null);
    const form = useForm<BoxOfficeRequest>({
        initialValues: {
            name: '',
            description: '',
            product_ids: [],
            event_occurrence_id: null,
            check_in_list_id: null,
            allow_price_override: false,
            allow_discounts: true,
            collect_order_questions: false,
            activates_at: '',
            expires_at: '',
        }
    });
    const createMutation = useCreateBoxOffice();
    const eventHasProducts = event?.product_categories?.some(category => (category.products?.length ?? 0) > 0);

    const handleSubmit = (requestData: BoxOfficeRequest) => {
        createMutation.mutate({
            eventId: eventId,
            boxOfficeData: requestData,
        }, {
            onSuccess: ({data}) => setCreated(data),
            onError: (error) => errorHandler(form, error),
        })
    }

    if (created) {
        return (
            <BoxOfficePinModal
                onClose={onClose}
                boxOfficeName={created.name}
                boxOfficeShortId={created.short_id}
                pin={created.pin}
                mode="created"
            />
        );
    }

    return (
        <Modal opened onClose={onClose} heading={null}>
            {!eventHasProducts && (
                <NoResultsSplash
                    imageHref={'/blank-slate/tickets.svg'}
                    heading={t`Please create a ticket`}
                    subHeading={(
                        <>
                            <p>
                                {t`You'll need a ticket or product before you can sell at the door.`}
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
            )}
            {eventHasProducts && event && (
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <BoxOfficeForm
                        form={form}
                        productCategories={event.product_categories as ProductCategory[]}
                        checkInLists={checkInLists?.data ?? []}
                        eventType={event.type as EventType}
                        occurrences={event.occurrences}
                        timezone={event.timezone}
                    />
                    <Button
                        type={'submit'}
                        fullWidth
                        loading={createMutation.isPending}
                        mt="md"
                        data-testid="box-office-submit-button"
                    >
                        {t`Create Box Office`}
                    </Button>
                </form>
            )}
        </Modal>
    );
}
