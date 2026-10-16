import {useForm} from "@mantine/form";
import {GenericModalProps, InviteUserRequest, TeamAccessRequest} from "../../../types.ts";
import {Modal, ModalFooter} from "../../common/Modal";
import {Button, SimpleGrid, TextInput} from "@mantine/core";
import {IconSend} from "@tabler/icons-react";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {t, Trans} from "@lingui/macro";
import {useInviteUser} from "../../../mutations/useInviteUser.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {
    TeamAccessFields,
    teamAccessInitialValues,
    TeamFormSection,
    toTeamAccessPayload
} from "../../common/TeamAccessFields";

interface InviteUserModalProps extends GenericModalProps {
    initialAccess?: Partial<TeamAccessRequest>;
}

export const InviteUserModal = ({onClose, initialAccess}: InviteUserModalProps) => {
    const createMutation = useInviteUser();
    const formErrorHandler = useFormErrorResponseHandler();

    const form = useForm<InviteUserRequest>({
        initialValues: {
            email: '',
            first_name: '',
            last_name: '',
            role: 'ORGANIZER',
            ...teamAccessInitialValues(),
            ...initialAccess,
        },
    });

    const handleCreate = (values: InviteUserRequest) => {
        createMutation.mutate({
            inviteUserData: {...values, ...toTeamAccessPayload(values)},
        }, {
            onSuccess: () => {
                form.reset();
                onClose();
                showSuccess(<Trans>Success! {values.first_name} will receive an email shortly.</Trans>);
            },
            onError: (error: any) => formErrorHandler(form, error)
        });
    };

    return (
        <Modal heading={t`Invite a team member`} onClose={onClose} opened size="lg">
            <form onSubmit={form.onSubmit(values => handleCreate(values))}>
                <TeamFormSection title={t`Details`}>
                    <SimpleGrid cols={{base: 1, xs: 2}} spacing="sm" verticalSpacing="sm">
                        <TextInput required {...form.getInputProps('first_name')} label={t`First Name`}/>
                        <TextInput {...form.getInputProps('last_name')} label={t`Last Name`}/>
                    </SimpleGrid>
                    <TextInput
                        required
                        type={'email'}
                        {...form.getInputProps('email')}
                        label={t`Email`}
                        description={t`We'll email them a link to join your account.`}
                    />
                </TeamFormSection>

                <TeamAccessFields form={form}/>

                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button
                        leftSection={<IconSend size={16}/>}
                        loading={createMutation.isPending}
                        type={'submit'}
                        data-testid="invite-team-member-submit">
                        {t`Send invitation`}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    )
}
