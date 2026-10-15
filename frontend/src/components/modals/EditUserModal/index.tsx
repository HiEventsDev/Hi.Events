import {useForm} from "@mantine/form";
import {GenericModalProps, TeamAccessRequest, User,} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {Avatar, Badge, Button, SimpleGrid, TextInput} from "@mantine/core";
import {Callout} from "../../common/Callout";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {t, Trans} from "@lingui/macro";
import {
    TeamAccessFields,
    teamAccessInitialValues,
    TeamFormFooter,
    TeamFormSection,
    toTeamAccessPayload
} from "../../common/TeamAccessFields";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {UpdateUserRequest} from "../../../api/user.client.ts";
import {useEditUser} from "../../../mutations/useEditUser.ts";
import {NavLink} from "react-router";
import {getInitials} from "../../../utilites/helpers.ts";
import classes from "./EditUserModal.module.scss";

interface EditUserModalProps extends GenericModalProps {
    user: User;
}

export const EditUserModal = ({onClose, user}: EditUserModalProps) => {
    const editMutation = useEditUser();
    const isLocked = !!user.is_account_owner || user.role === 'SUPERADMIN';
    const formErrorHandler = useFormErrorResponseHandler();

    const form = useForm<UpdateUserRequest & TeamAccessRequest>({
        initialValues: {
            first_name: user.first_name,
            last_name: user.last_name,
            role: user.role ?? 'ORGANIZER',
            ...teamAccessInitialValues(user),
        },
    });

    const handleSave = (values: UpdateUserRequest & TeamAccessRequest) => {
        editMutation.mutate({
            userId: user.id,
            userData: {...values, ...toTeamAccessPayload(values)},
        }, {
            onSuccess: () => {
                form.reset();
                onClose();
                showSuccess(t`Changes saved`);
            },
            onError: (error) => formErrorHandler(form, error)
        });
    };

    return (
        <Modal heading={t`Edit member`} onClose={onClose} opened size="lg">
            <div className={classes.member}>
                <Avatar size={40} radius="xl" color="primary">
                    {getInitials(`${user.first_name} ${user.last_name ?? ''}`)}
                </Avatar>
                <div className={classes.memberText}>
                    <span className={classes.memberName}>{user.first_name} {user.last_name}</span>
                    <span className={classes.memberEmail}>{user.email}</span>
                </div>
                {user.is_account_owner && <Badge variant="light" color="gray">{t`Owner`}</Badge>}
                {user.status === 'INVITED' && <Badge variant="light" color="orange">{t`Invited`}</Badge>}
                {user.status === 'INACTIVE' && <Badge variant="light" color="red">{t`Inactive`}</Badge>}
            </div>

            {user.status === 'INVITED' && (
                <Callout variant="info">
                    <Trans>They haven't accepted their invitation yet. Changes apply once they join.</Trans>
                </Callout>
            )}

            {user.is_account_owner && (
                <Callout variant="info">
                    {t`The account owner's role and access can't be changed.`}
                </Callout>
            )}

            <form onSubmit={form.onSubmit(values => handleSave(values))}>
                <fieldset disabled={editMutation.isPending} className={classes.fieldset}>
                    <TeamFormSection title={t`Details`}>
                        <SimpleGrid cols={{base: 1, xs: 2}} spacing="sm" verticalSpacing="sm">
                            <TextInput required {...form.getInputProps('first_name')} label={t`First Name`}/>
                            <TextInput required {...form.getInputProps('last_name')} label={t`Last Name`}/>
                        </SimpleGrid>
                        <p className={classes.hint}>
                            <Trans>Members change their own email in <NavLink target={'_blank'} to={'/manage/profile'}>profile settings</NavLink>.</Trans>
                        </p>
                    </TeamFormSection>

                    <TeamAccessFields form={form} disabled={isLocked}/>
                </fieldset>

                <TeamFormFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button
                        loading={editMutation.isPending}
                        type={'submit'}
                        data-testid="edit-team-member-submit">
                        {t`Save changes`}
                    </Button>
                </TeamFormFooter>
            </form>
        </Modal>
    )
}
