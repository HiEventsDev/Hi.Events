import {CheckInListRequest, GenericModalProps, IdParam, ProductCategory} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {CheckInListForm} from "../../forms/CheckInListForm";
import {useForm} from "@mantine/form";
import {Alert, Button, Center, Loader} from "@mantine/core";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {useParams} from "react-router";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useEditCheckInList} from "../../../mutations/useEditCheckInList.ts";
import {useGetEventCheckInList} from "../../../queries/useGetCheckInList.ts";
import {useEffect} from "react";
import {utcToTz} from "../../../utilites/dates.ts";

interface EditCheckInListModalProps {
    checkInListId: IdParam;
}

export const EditCheckInListModal = ({
                                         onClose,
                                         checkInListId
                                     }: GenericModalProps & EditCheckInListModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const {data: checkInList, error: checkInListError, isLoading: checkInListLoading} = useGetEventCheckInList(
        checkInListId,
        eventId
    );
    const {data: event} = useGetEvent(eventId);
    const form = useForm<CheckInListRequest>({
        initialValues: {
            name: '',
            expires_at: '',
            activates_at: '',
            description: '',
            product_ids: [],
        }
    });
    const editMutation = useEditCheckInList();

    const handleSubmit = (requestData: CheckInListRequest) => {
        editMutation.mutate({
            eventId: eventId,
            checkInListData: requestData,
            checkInListId: checkInListId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully updated Check-In List`);
                onClose();
            },
            onError: (error) => errorHandler(form, error),
        })
    }

    useEffect(() => {
        if (checkInList && event) {
            form.setValues({
                name: checkInList.name,
                description: checkInList.description,
                expires_at: utcToTz(checkInList.expires_at, event.timezone),
                activates_at: utcToTz(checkInList.activates_at, event.timezone),
                product_ids: checkInList.products?.map(product => String(product.id)),
            });
        }
    }, [checkInList]);

    return (
        <Modal opened onClose={onClose} heading={t`Edit Check-In List`}>

            {checkInListLoading && (
                <Center>
                    <Loader/>
                </Center>
            )}

            {!!checkInListError && (
                <Alert color={'red'}>
                    {t`Failed to load Check-In List`}
                </Alert>
            )}

            {event && checkInList && (
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <CheckInListForm
                        form={form}
                        productCategories={event.product_categories as ProductCategory[]}
                    />
                    <Button
                        type={'submit'}
                        fullWidth
                        loading={editMutation.isPending}
                        mt="md"
                    >
                        {t`Edit Check-In List`}
                    </Button>
                </form>
            )}
        </Modal>
    );
}
