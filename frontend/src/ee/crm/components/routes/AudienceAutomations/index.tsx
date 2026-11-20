import {useState} from "react";
import {useParams} from "react-router";
import {plural, t} from "@lingui/macro";
import {ActionIcon, Button, Menu, Skeleton, Switch} from "@mantine/core";
import {IconDotsVertical, IconPlus, IconTrash} from "@tabler/icons-react";
import {CreateButton} from "../../../../../components/common/CreateButton";
import {Modal} from "../../../../../components/common/Modal";
import {useGetOrganizer} from "../../../../../queries/useGetOrganizer.ts";
import {firstApiError} from "../../../../../utilites/apiErrors.ts";
import {confirmationDialog} from "../../../../../utilites/confirmationDialog.tsx";
import {relativeDate} from "../../../../../utilites/dates.ts";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";
import {useCrmAccess} from "../../../hooks/useCrmAccess.ts";
import {useCrmReady} from "../../../hooks/useCrmReady.ts";
import {useDeleteCrmAutomation, useSetCrmAutomationEnabled} from "../../../mutations/useCrmAutomationMutations.ts";
import {useGetCrmAutomations} from "../../../queries/useGetCrmAutomations.ts";
import {useGetCrmAutomationTemplates} from "../../../queries/useGetCrmAutomationTemplates.ts";
import {useGetCrmSegments} from "../../../queries/useGetCrmSegments.ts";
import {CrmAutomationDraft} from "../../../api/crm.client.ts";
import {CrmAutomation, CrmAutomationTemplate} from "../../../types.ts";
import {automationSummary, automationTitle, presetLabel} from "../../../utilites/labels.ts";
import {AudienceShell} from "../../AudienceShell";
import {LoadError} from "../../LoadError";
import {StatusBadge} from "../../../../../components/common/StatusBadge";
import sharedClasses from "../../Audience.module.scss";
import {AutomationModal} from "./AutomationModal.tsx";
import {ConfirmAutomationModal} from "./ConfirmAutomationModal.tsx";
import {automationFailureReason} from "./failureReason.ts";
import {TriggerIcon} from "./TriggerIcon.tsx";
import {TriggerPicker} from "./TriggerPicker.tsx";
import classes from "./Automations.module.scss";
import {Notice} from "../../Notice";

type Editing = {automation: CrmAutomation} | {template: CrmAutomationTemplate};

const automationDraft = (automation: CrmAutomation): CrmAutomationDraft => ({
    trigger: automation.trigger,
    subject: automation.subject,
    preheader: automation.preheader,
    body: automation.body,
    segment_id: automation.segment_id,
    preset: automation.preset,
    delay_days: automation.delay_days,
    wait_minutes: automation.wait_minutes,
    days_before: automation.days_before,
    occurrence_audience: automation.occurrence_audience,
});

const AudienceAutomations = () => {
    const {organizerId} = useParams();
    const isReady = useCrmReady(organizerId);
    const {canChange, canRemove, isLocked} = useCrmAccess();
    const automationsQuery = useGetCrmAutomations(organizerId, isReady);
    const templatesQuery = useGetCrmAutomationTemplates(organizerId, isReady);
    const segmentsQuery = useGetCrmSegments(organizerId, isReady);
    const setEnabled = useSetCrmAutomationEnabled();
    const deleteAutomation = useDeleteCrmAutomation();
    const [editing, setEditing] = useState<Editing | null>(null);
    const [isPicking, setIsPicking] = useState(false);
    const [confirming, setConfirming] = useState<CrmAutomation | null>(null);
    const {data: organizer} = useGetOrganizer(organizerId);
    const timezone = organizer?.timezone ?? 'UTC';
    const confirmingName = confirming?.name ?? '';
    const automations = automationsQuery.data ?? [];
    const templates = templatesQuery.data ?? [];
    const hasControls = canChange || canRemove;
    const picker = templatesQuery.isError
        ? <LoadError message={t`We couldn't load the automations you can set up.`} onRetry={() => templatesQuery.refetch()}/>
        : <TriggerPicker templates={templates} onPick={canChange ? template => {
            setIsPicking(false);
            setEditing({template});
        } : undefined}/>;

    const audienceLabel = (automation: CrmAutomation): string => {
        if (automation.segment_id) {
            return segmentsQuery.data?.data.find(segment => segment.id === automation.segment_id)?.name ?? t`Saved segment`;
        }
        return automation.preset ? presetLabel(automation.preset) : t`Everyone who opted in`;
    };

    const toggle = (automation: CrmAutomation, isEnabled: boolean) => setEnabled.mutate({
        organizerId,
        automationId: automation.id,
        isEnabled,
    }, {
        onSuccess: () => {
            setConfirming(null);
            showSuccess(isEnabled ? t`Automation turned on` : t`Automation turned off`);
        },
        onError: error => showError(firstApiError(error, t`We couldn't update this automation. Please try again.`)),
    });

    const remove = (automation: CrmAutomation) => {
        const name = automation.name;

        confirmationDialog(
            t`Delete the automation "${name}"?`,
            () => deleteAutomation.mutate({organizerId, automationId: automation.id}, {
                onSuccess: () => showSuccess(t`Automation deleted`),
                onError: error => showError(firstApiError(error, t`We couldn't delete this automation. Please try again.`)),
            }),
            {
                confirm: t`Delete automation`,
                destructive: true,
                description: t`It stops sending straight away. Emails it already sent stay in your campaign history.`,
            },
        );
    };

    return (
        <AudienceShell
            actions={canChange && automations.length > 0 ? (
                <CreateButton color="green" rightSection={<IconPlus/>} onClick={() => setIsPicking(true)} data-testid="automation-new-button">
                    {t`New automation`}
                </CreateButton>
            ) : undefined}
        >
            {isLocked && automations.some(automation => automation.is_enabled) && (
                <Notice title={t`Automations are paused`}>
                    {t`Automations don't send while your licence is inactive. They pick up again once it's renewed.`}
                </Notice>
            )}

            <section className={sharedClasses.panel}>
                <div className={sharedClasses.panelFlush}>
                    {(automationsQuery.isLoading || (automations.length === 0 && templatesQuery.isLoading)) && <Skeleton height={240} m="md" radius="md"/>}

                    {automationsQuery.isError && (
                        <LoadError message={t`We couldn't load your automations.`} onRetry={() => automationsQuery.refetch()}/>
                    )}

                    {automationsQuery.isSuccess && automations.length === 0 && !templatesQuery.isLoading && (
                        <>
                            <div className={sharedClasses.firstRun}>
                                {canChange && <h2 className={sharedClasses.panelTitle}>{t`Set up your first automation`}</h2>}
                                <p>
                                    {canChange
                                        ? t`Pick one to start from. Each comes with an email you can edit, only goes to people who opted in, and stays off until you turn it on.`
                                        : t`No automations yet. These are the ones that can be set up. Each only goes to people who opted in.`}
                                </p>
                            </div>
                            {picker}
                        </>
                    )}

                    {automations.map(automation => {
                        const lastSent = automation.last_sent_at ? relativeDate(automation.last_sent_at) : '';
                        const isPaused = isLocked && automation.is_enabled;

                        return (
                            <div key={automation.id} className={classes.automation} data-testid="automation-card">
                                <span className={classes.automationIcon} data-on={automation.is_enabled || undefined}>
                                    <TriggerIcon trigger={automation.trigger}/>
                                </span>
                                <div className={classes.automationText}>
                                    <h3>
                                        {automation.name}
                                        {(isPaused || !hasControls) && (
                                            <StatusBadge
                                                className={classes.automationStatus}
                                                tone={isPaused ? 'waiting' : automation.is_enabled ? 'positive' : 'neutral'}
                                            >
                                                {isPaused ? t`Paused` : automation.is_enabled ? t`On` : t`Off`}
                                            </StatusBadge>
                                        )}
                                    </h3>
                                    {automation.name !== automationTitle(automation.trigger) && (
                                        <span className={classes.triggerLabel}>{automationTitle(automation.trigger)}</span>
                                    )}
                                    <p>{automationSummary(automation, audienceLabel(automation))}</p>
                                    <div className={classes.automationMeta}>
                                        <span>
                                            {automation.sent_count > 0 && automation.last_sent_at
                                                ? plural(automation.sent_count, {
                                                    one: `# email sent, most recently ${lastSent}`,
                                                    other: `# emails sent, most recently ${lastSent}`,
                                                })
                                                : t`Nothing sent yet.`}
                                        </span>
                                    </div>
                                    {automation.is_enabled && automation.last_failure_reason && (
                                        <Notice
                                            tone={automation.last_failure_reason === 'EVENT_AWAITING_REVIEW' ? 'info' : 'danger'}
                                            title={automation.last_failure_reason === 'EVENT_AWAITING_REVIEW' ? t`Waiting to send` : t`The last one couldn't be sent`}
                                            className={classes.automationWarning}
                                        >
                                            {automationFailureReason(automation.last_failure_reason, automation.last_failure_reason_params)}
                                        </Notice>
                                    )}
                                </div>
                                {hasControls && (
                                    <div className={classes.automationActions}>
                                        {canChange && (
                                            <Button variant="default" size="sm" onClick={() => setEditing({automation})} data-testid="automation-edit">
                                                {t`Edit`}
                                            </Button>
                                        )}
                                        <Switch
                                            aria-label={automation.name}
                                            checked={automation.is_enabled}
                                            disabled={setEnabled.isPending || (!automation.is_enabled && !canChange)}
                                            onChange={event => event.currentTarget.checked ? setConfirming(automation) : toggle(automation, false)}
                                        />
                                        {canRemove && (
                                            <Menu position="bottom-end" shadow="md" withinPortal>
                                                <Menu.Target>
                                                    <ActionIcon variant="subtle" color="gray" size="input-sm" aria-label={t`More actions`} data-testid="automation-menu">
                                                        <IconDotsVertical size={16}/>
                                                    </ActionIcon>
                                                </Menu.Target>
                                                <Menu.Dropdown>
                                                    <Menu.Item color="red" leftSection={<IconTrash size={14}/>} onClick={() => remove(automation)} data-testid="automation-delete">
                                                        {t`Delete`}
                                                    </Menu.Item>
                                                </Menu.Dropdown>
                                            </Menu>
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </section>

            {isPicking && (
                <Modal opened onClose={() => setIsPicking(false)} heading={t`New automation`} size="lg">
                    {templatesQuery.isLoading ? <Skeleton height={240} radius="md"/> : <div className={classes.pickerInModal}>{picker}</div>}
                </Modal>
            )}

            {editing && (
                <AutomationModal
                    organizerId={organizerId}
                    automation={'automation' in editing ? editing.automation : undefined}
                    template={'template' in editing ? editing.template : undefined}
                    timezone={timezone}
                    onClose={() => setEditing(null)}
                />
            )}

            {confirming && (
                <ConfirmAutomationModal
                    organizerId={organizerId}
                    draft={automationDraft(confirming)}
                    automationId={confirming.id}
                    isRunning={false}
                    heading={t`Turn on "${confirmingName}"?`}
                    confirmLabel={t`Turn on`}
                    cancelLabel={t`Cancel`}
                    timezone={timezone}
                    isConfirming={setEnabled.isPending}
                    onConfirm={() => toggle(confirming, true)}
                    onClose={() => setConfirming(null)}
                />
            )}
        </AudienceShell>
    );
};

export default AudienceAutomations;
