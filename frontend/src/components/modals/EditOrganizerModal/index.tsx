import {GenericModalProps, IdParam, Organizer} from "../../../types.ts";
import {t} from "@lingui/macro";
import {Modal} from "../../common/Modal";
import {useForm} from "@mantine/form";
import {useUpdateOrganizer} from "../../../mutations/useUpdateOrganizer.ts";
import {Button, Group} from "@mantine/core";
import {OrganizerForm} from "../../forms/OrganizerForm";
import {useGetOrganizer} from "../../../queries/useGetOrganizer.ts";
import {useEffect} from "react";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";

interface EditOrganizerModalProps extends GenericModalProps {
    organizerId: IdParam;
    onClose: () => void;
}

export const EditOrganizerModal = ({organizerId, onClose}: EditOrganizerModalProps) => {
    const {data: organizer, isFetched} = useGetOrganizer(organizerId);
    const formResponseHandler = useFormErrorResponseHandler();

    const form = useForm({
        initialValues: {
            name: '',
            email: '',
            currency: '',
            timezone: '',
        }
    });

    useEffect(() => {
        if (!organizer) {
            return;
        }

        form.setValues({
            name: organizer.name,
            email: organizer.email,
            currency: organizer.currency as string,
            timezone: organizer.timezone as string,
        });
    }, [organizer, isFetched]);

    const organizerMutation = useUpdateOrganizer();

    const handleSubmit = (values: Partial<Organizer>) => {
        organizerMutation.mutate({
            organizerData: values,
            organizerId: organizerId,
        }, {
            onSuccess: () => {
                onClose();
            },
            onError: (error: any) => {
                formResponseHandler(form, error);
            }
        });
    }

    return (
        <Modal
            opened
            onClose={onClose}
            heading={t`Edit Organizer`}
        >
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <OrganizerForm form={form as any}/>
                <Group gap={10}>
                    <Button fullWidth loading={organizerMutation.isPending}
                            type={'submit'}
                    >{t`Save Organizer`}
                    </Button>
                </Group>
            </form>
        </Modal>
    );

}
