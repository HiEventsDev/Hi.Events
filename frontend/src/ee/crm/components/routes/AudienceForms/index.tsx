import {useState} from "react";
import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {ActionIcon, Button, Menu} from "@mantine/core";
import {IconDotsVertical, IconPencil, IconPlayerPause, IconPlayerPlay, IconPlus, IconShare, IconTrash} from "@tabler/icons-react";
import {CreateButton} from "../../../../../components/common/CreateButton";
import {TableSkeleton} from "../../../../../components/common/TableSkeleton";
import {useGetAccount} from "../../../../../queries/useGetAccount.ts";
import {useGetOrganizer} from "../../../../../queries/useGetOrganizer.ts";
import {OrganizerStatus} from "../../../../../types.ts";
import {firstApiError} from "../../../../../utilites/apiErrors.ts";
import {confirmationDialog} from "../../../../../utilites/confirmationDialog.tsx";
import {relativeDate} from "../../../../../utilites/dates.ts";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";
import {useCrmAccess} from "../../../hooks/useCrmAccess.ts";
import {useCrmReady} from "../../../hooks/useCrmReady.ts";
import {useDeleteCrmSignupForm, useUpdateCrmSignupForm} from "../../../mutations/useCrmSignupFormMutations.ts";
import {useGetCrmSignupForms} from "../../../queries/useCrmSignupFormQueries.ts";
import {CrmSignupForm} from "../../../types.ts";
import {formatCount} from "../../../utilites/format.ts";
import {signupFormRequest} from "../../../utilites/signupForms.ts";
import {AudienceShell} from "../../AudienceShell";
import {LoadError} from "../../LoadError";
import {StatusBadge} from "../../../../../components/common/StatusBadge";
import shared from "../../Audience.module.scss";
import {ShareSignupFormModal} from "./ShareSignupFormModal.tsx";
import {SignupFormModal} from "./SignupFormModal.tsx";
import classes from "./Forms.module.scss";
import {Notice} from "../../Notice";

type PublishBlocker = 'ACCOUNT_NOT_VERIFIED' | 'ORGANIZER_NOT_LIVE';

const AudienceForms = () => {
    const {organizerId} = useParams();
    const {data: account} = useGetAccount();
    const {data: organizer} = useGetOrganizer(organizerId);
    const isReady = useCrmReady(organizerId);
    const {canChange, canRemove} = useCrmAccess();
    const formsQuery = useGetCrmSignupForms(organizerId, isReady);
    const updateForm = useUpdateCrmSignupForm();
    const deleteForm = useDeleteCrmSignupForm();
    const [editing, setEditing] = useState<{form?: CrmSignupForm} | null>(null);
    const [sharing, setSharing] = useState<CrmSignupForm | null>(null);
    const forms = formsQuery.data ?? [];
    const publishBlocker = ((): PublishBlocker | null => {
        if (account && !account.is_account_email_confirmed) {
            return 'ACCOUNT_NOT_VERIFIED';
        }
        return organizer && organizer.status !== OrganizerStatus.LIVE ? 'ORGANIZER_NOT_LIVE' : null;
    })();

    const setPaused = (form: CrmSignupForm, isPaused: boolean) => updateForm.mutate({
        organizerId,
        formId: form.id,
        form: {...signupFormRequest(form), status: isPaused ? 'PAUSED' : 'ACTIVE'},
    }, {
        onSuccess: () => showSuccess(isPaused ? t`Form paused` : t`Form is live again`),
        onError: error => showError(firstApiError(error, t`We couldn't update this form. Please try again.`)),
    });

    const remove = (form: CrmSignupForm) => {
        const name = form.name;

        confirmationDialog(
            t`Delete the form "${name}"?`,
            () => deleteForm.mutate({organizerId, formId: form.id}, {
                onSuccess: () => showSuccess(t`Form deleted`),
                onError: error => showError(firstApiError(error, t`We couldn't delete this form. Please try again.`)),
            }),
            {
                confirm: t`Delete form`,
                destructive: true,
                description: t`Its link and embeds stop working straight away. Everyone who subscribed through it stays subscribed, and anyone who still has a confirmation email can confirm.`,
            },
        );
    };

    return (
        <AudienceShell
            actions={canChange && (
                <CreateButton color="green" rightSection={<IconPlus/>} onClick={() => setEditing({})} data-testid="signup-form-create-button">
                    {t`New form`}
                </CreateButton>
            )}
        >
            <TableSkeleton isVisible={formsQuery.isLoading} numRows={3}/>

            {formsQuery.isSuccess && publishBlocker === 'ACCOUNT_NOT_VERIFIED' && (
                <Notice
                    title={t`Confirm your email address to take signups`}
                >
                    {t`Signup forms only work once your account's email address is confirmed. Check your inbox for the confirmation email. Until then, links and embeds say the form isn't available, and new forms are saved as paused.`}
                </Notice>
            )}

            {formsQuery.isSuccess && publishBlocker === 'ORGANIZER_NOT_LIVE' && (
                <Notice
                    title={t`Publish your organizer page to take signups`}
                >
                    {t`Signup forms only work while your organizer page is published. Until then, links and embeds say the form isn't available, and new forms are saved as paused.`}
                </Notice>
            )}

            {formsQuery.isError && (
                <div className={shared.panel}>
                    <LoadError message={t`We couldn't load your forms.`} onRetry={() => formsQuery.refetch()}/>
                </div>
            )}

            {formsQuery.isSuccess && forms.length === 0 && (
                <div className={shared.panel}>
                    <div className={shared.empty}>
                        <img className={shared.emptyImage} src="/blank-slate/audience-signup-forms.svg" alt=""/>
                        <p>{t`No signup forms yet. Create one to collect subscribers from a link, your website or your organizer page.`}</p>
                        {canChange && (
                            <CreateButton variant="default" size="xs" onClick={() => setEditing({})}>{t`New form`}</CreateButton>
                        )}
                    </div>
                </div>
            )}

            {forms.length > 0 && (
                <ul className={`${shared.panel} ${classes.list}`}>
                    {forms.map(form => {
                        const isPaused = form.status === 'PAUSED';
                        const isHidden = !isPaused && publishBlocker !== null;

                        return (
                            <li key={form.id} className={classes.form} data-testid="signup-form-row" data-paused={isPaused || undefined}>
                                <div className={classes.identity}>
                                    <span className={classes.nameLine}>
                                        <strong>{form.name}</strong>
                                        <StatusBadge className={classes.status} tone={isPaused ? 'neutral' : isHidden ? 'waiting' : 'positive'}>
                                            {isPaused ? t`Paused` : isHidden ? t`Hidden` : t`Live`}
                                        </StatusBadge>
                                    </span>
                                    <small>{form.title}</small>
                                    {isPaused && <small>{t`Resume this form to share it`}</small>}
                                    {isHidden && (
                                        <small>
                                            {publishBlocker === 'ACCOUNT_NOT_VERIFIED'
                                                ? t`Hidden until your email address is confirmed`
                                                : t`Hidden until your organizer page is published`}
                                        </small>
                                    )}
                                    {!isPaused && !isHidden && form.show_on_organizer_page && <small>{t`On your organizer page`}</small>}
                                </div>
                                <dl className={classes.stats}>
                                    <div>
                                        <dt>{t`Signups started`}</dt>
                                        <dd data-testid="signup-form-started">{formatCount(form.submissions_count)}</dd>
                                    </div>
                                    <div>
                                        <dt>{t`Subscribers`}</dt>
                                        <dd data-testid="signup-form-subscribers">{formatCount(form.confirmations_count)}</dd>
                                    </div>
                                    <div>
                                        <dt>{t`Waiting to confirm`}</dt>
                                        <dd data-testid="signup-form-waiting">{formatCount(form.waiting_count ?? 0)}</dd>
                                    </div>
                                    <div>
                                        <dt>{t`Last confirmed`}</dt>
                                        <dd className={classes.when}>
                                            {form.last_confirmed_at ? relativeDate(form.last_confirmed_at) : t`None yet`}
                                        </dd>
                                    </div>
                                </dl>
                                <div className={classes.actions}>
                                    <Button
                                        variant="default"
                                        size="sm"
                                        leftSection={<IconShare size={14}/>}
                                        onClick={() => setSharing(form)}
                                        disabled={isPaused || isHidden}
                                        data-testid="signup-form-share"
                                    >
                                        {t`Share`}
                                    </Button>
                                    {(canChange || canRemove) && (
                                        <Menu position="bottom-end" shadow="md" withinPortal>
                                            <Menu.Target>
                                                <ActionIcon variant="subtle" color="gray" size="input-sm" aria-label={t`Form actions`}>
                                                    <IconDotsVertical size={16}/>
                                                </ActionIcon>
                                            </Menu.Target>
                                            <Menu.Dropdown>
                                                {canChange && (
                                                    <>
                                                        <Menu.Item leftSection={<IconPencil size={14}/>} onClick={() => setEditing({form})}>
                                                            {t`Edit`}
                                                        </Menu.Item>
                                                        <Menu.Item
                                                            leftSection={isPaused ? <IconPlayerPlay size={14}/> : <IconPlayerPause size={14}/>}
                                                            onClick={() => setPaused(form, !isPaused)}
                                                            disabled={isPaused && publishBlocker !== null}
                                                        >
                                                            {isPaused ? t`Resume` : t`Pause`}
                                                        </Menu.Item>
                                                    </>
                                                )}
                                                {canRemove && (
                                                    <Menu.Item color="red" leftSection={<IconTrash size={14}/>} onClick={() => remove(form)}>
                                                        {t`Delete`}
                                                    </Menu.Item>
                                                )}
                                            </Menu.Dropdown>
                                        </Menu>
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}

            {editing && (
                <SignupFormModal
                    organizerId={organizerId}
                    form={editing.form}
                    otherFormOnOrganizerPage={forms.find(form => form.show_on_organizer_page && form.id !== editing.form?.id)}
                    savesNewFormAsPaused={publishBlocker !== null}
                    onClose={() => setEditing(null)}
                    onCreated={form => setSharing(form)}
                />
            )}

            {sharing && <ShareSignupFormModal form={sharing} onClose={() => setSharing(null)}/>}
        </AudienceShell>
    );
};

export default AudienceForms;
