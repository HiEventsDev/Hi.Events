import {useForm} from "@mantine/form";
import {GenericModalProps, InviteUserRequest,} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {Button, SimpleGrid, TextInput} from "@mantine/core";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {t, Trans} from "@lingui/macro";
import {useInviteUser} from "../../../mutations/useInviteUser.ts";
import {CustomSelect, ItemProps} from "../../common/CustomSelect";
import {IconUser, IconUserShield} from "@tabler/icons-react";
import {showSuccess} from "../../../utilites/notifications.tsx";

export const InviteUserModal = ({onClose}: GenericModalProps) => {
    const createMutation = useInviteUser();
    const formErrorHandler = useFormErrorResponseHandler();

    const form = useForm<InviteUserRequest>({
        initialValues: {
            email: '',
            first_name: '',
            last_name: '',
            role: 'ADMIN',
        },
    });

    const handleCreate = (values: InviteUserRequest) => {
        createMutation.mutate({
            inviteUserData: values,
        }, {
            onSuccess: () => {
                form.reset();
                onClose();
                showSuccess(<Trans>Success! {values.first_name} will receive an email shortly.</Trans>);
            },
            onError: (error: any) => formErrorHandler(form, error)
        });
    };

    const calcTypeOptions: ItemProps[] = [
        {
            icon: <IconUserShield/>,
            label: t`Admin`,
            value: 'ADMIN',
            description: t`Admin users have full access to events and account settings.`,
        },
        {
            icon: <IconUser/>,
            label: t`Organizer`,
            value: 'ORGANIZER',
            description: t`Organizers can only manage events and tickets. They cannot manage users, account settings or billing information.`,
        },
    ];

    return (
        <Modal heading={t`Invite User`} onClose={onClose} opened>
            <form onSubmit={form.onSubmit(values => handleCreate(values))}>
                <SimpleGrid cols={2} >
                    <TextInput required {...form.getInputProps('first_name')} label={t`First Name`}/>
                    <TextInput required {...form.getInputProps('last_name')} label={t`Last Name`}/>
                </SimpleGrid>

                <TextInput required type={'email'}  {...form.getInputProps('email')} label={t`Email`}/>

                <CustomSelect
                    label={t`Role`}
                    optionList={calcTypeOptions}
                    form={form}
                    name={'role'}
                />

                <Button
                    fullWidth
                    loading={createMutation.isPending}
                    type={'submit'}>
                    {t`Invite User`}
                </Button>
            </form>
        </Modal>
    )
}
