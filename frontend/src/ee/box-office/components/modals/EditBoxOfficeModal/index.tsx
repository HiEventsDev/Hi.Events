import {BoxOfficeRequest, EventType, GenericModalProps, IdParam, ProductCategory} from "../../../../../types.ts";
import {Modal} from "../../../../../components/common/Modal";
import {t} from "@lingui/macro";
import {BoxOfficeForm} from "../../forms/BoxOfficeForm";
import {useForm} from "@mantine/form";
import {Alert, Button, Center, Loader} from "@mantine/core";
import {showSuccess} from "../../../../../utilites/notifications.tsx";
import {useParams} from "react-router";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {useGetEventCheckInLists} from "../../../../../queries/useGetCheckInLists.ts";
import {useEditBoxOffice} from "../../../mutations/useEditBoxOffice.ts";
import {useGetBoxOffice} from "../../../queries/useGetBoxOffice.ts";
import {useEffect} from "react";
import {utcToTz} from "../../../../../utilites/dates.ts";

interface EditBoxOfficeModalProps {
    boxOfficeId: IdParam;
}

export const EditBoxOfficeModal = ({onClose, boxOfficeId}: GenericModalProps & EditBoxOfficeModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const {data: boxOffice, error: boxOfficeError, isLoading: boxOfficeLoading} = useGetBoxOffice(eventId, boxOfficeId);
    const {data: event} = useGetEvent(eventId);
    const {data: checkInLists} = useGetEventCheckInLists(eventId, {perPage: 100});
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
    const editMutation = useEditBoxOffice();

    const handleSubmit = (requestData: BoxOfficeRequest) => {
        editMutation.mutate({
            eventId: eventId,
            boxOfficeId: boxOfficeId,
            boxOfficeData: requestData,
        }, {
            onSuccess: () => {
                showSuccess(t`Box office updated`);
                onClose();
            },
            onError: (error) => errorHandler(form, error),
        })
    }

    useEffect(() => {
        if (boxOffice && event) {
            form.setValues({
                name: boxOffice.name,
                description: boxOffice.description ?? '',
                product_ids: boxOffice.products?.map(product => String(product.id)) ?? [],
                event_occurrence_id: boxOffice.event_occurrence_id ?? null,
                check_in_list_id: boxOffice.check_in_list_id ?? null,
                allow_price_override: boxOffice.allow_price_override,
                allow_discounts: boxOffice.allow_discounts,
                collect_order_questions: boxOffice.collect_order_questions,
                activates_at: utcToTz(boxOffice.activates_at ?? undefined, event.timezone),
                expires_at: utcToTz(boxOffice.expires_at ?? undefined, event.timezone),
            });
        }
    }, [boxOffice]);

    return (
        <Modal opened onClose={onClose} heading={t`Edit Box Office`}>
            {boxOfficeLoading && (
                <Center>
                    <Loader/>
                </Center>
            )}

            {!!boxOfficeError && (
                <Alert color={'red'}>
                    {t`Failed to load box office`}
                </Alert>
            )}

            {event && boxOffice && (
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <BoxOfficeForm
                        form={form}
                        productCategories={event.product_categories as ProductCategory[]}
                        checkInLists={checkInLists?.data ?? []}
                        eventType={event.type as EventType}
                        occurrences={event.occurrences}
                        timezone={event.timezone}
                        hideIntro
                    />
                    <Button
                        type={'submit'}
                        fullWidth
                        loading={editMutation.isPending}
                        mt="md"
                        data-testid="box-office-submit-button"
                    >
                        {t`Save Box Office`}
                    </Button>
                </form>
            )}
        </Modal>
    );
}
