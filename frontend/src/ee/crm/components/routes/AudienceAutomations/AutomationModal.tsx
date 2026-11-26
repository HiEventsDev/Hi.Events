import {t} from "@lingui/macro";
import {useRef, useState} from "react";
import {Button, NumberInput, SegmentedControl, Select, Text, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {Editor} from "../../../../../components/common/Editor";
import {LiquidTokenMenu} from "../../../../../components/common/Editor/Controls/LiquidTokenControl";
import {Modal, ModalFooter} from "../../../../../components/common/Modal";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import {IdParam} from "../../../../../types.ts";
import {showSuccess} from "../../../../../utilites/notifications.tsx";
import {CrmAutomationRequest} from "../../../api/crm.client.ts";
import {useCreateCrmAutomation, useUpdateCrmAutomation} from "../../../mutations/useCrmAutomationMutations.ts";
import {useGetCrmSegments} from "../../../queries/useGetCrmSegments.ts";
import {CrmAutomation, CrmAutomationTemplate, CrmAutomationTrigger, CrmOccurrenceAudience} from "../../../types.ts";
import {parseSegmentParam} from "../../../utilites/format.ts";
import {hasMessageText} from "../../../utilites/messageBody.ts";
import {abandonedWaitLabel, automationTitle, presetLabel} from "../../../utilites/labels.ts";
import {automationTokens, insertAtCursor} from "../../../utilites/tokens.ts";
import {deletedSegmentOptions} from "../../../utilites/segmentOptions.ts";
import {InsertTokenMenu} from "../../InsertTokenMenu";
import {ConfirmAutomationModal} from "./ConfirmAutomationModal.tsx";
import classes from "./Automations.module.scss";

const WAIT_OPTIONS = [60, 180, 1440];

const ALL_SUBSCRIBERS = 'all';

const triggersWithAudience: CrmAutomationTrigger[] = ['EVENT_PUBLISHED', 'BEFORE_EVENT', 'ABANDONED_CHECKOUT', 'SEGMENT_ENTERED', 'WIN_BACK', 'WELCOME'];

const triggersAllowingEveryone: CrmAutomationTrigger[] = ['EVENT_PUBLISHED', 'BEFORE_EVENT', 'ABANDONED_CHECKOUT'];

type AutomationSource = CrmAutomation | CrmAutomationTemplate;

const audienceValue = (source: AutomationSource): string => {
    if ('segment_id' in source && source.segment_id) {
        return `saved:${source.segment_id}`;
    }
    return source.preset ? `preset:${source.preset}` : ALL_SUBSCRIBERS;
};

export const AutomationModal = ({organizerId, automation, template, timezone, onClose}: {
    organizerId: IdParam,
    automation?: CrmAutomation,
    template?: CrmAutomationTemplate,
    timezone: string,
    onClose: () => void,
}) => {
    const source = (automation ?? template) as AutomationSource;
    const trigger = source.trigger;
    const createAutomation = useCreateCrmAutomation();
    const updateAutomation = useUpdateCrmAutomation();
    const errorHandler = useFormErrorResponseHandler();
    const {data: segments} = useGetCrmSegments(organizerId);
    const tokens = automationTokens(trigger);
    const automationName = automation?.name ?? '';
    const subjectInput = useRef<HTMLInputElement>(null);
    const preheaderInput = useRef<HTMLInputElement>(null);
    const [pendingChanges, setPendingChanges] = useState<CrmAutomationRequest | null>(null);
    const form = useForm({
        initialValues: {
            name: source.name,
            subject: source.subject,
            preheader: 'preheader' in source ? source.preheader ?? '' : '',
            body: source.body,
            audience: audienceValue(source),
            delay_days: source.delay_days,
            wait_minutes: String(source.wait_minutes),
            days_before: source.days_before,
            occurrence_audience: source.occurrence_audience,
        },
        validate: {
            name: value => value.trim() ? null : t`Give this automation a name`,
            subject: value => value.trim() ? null : t`Add a subject line`,
            body: value => hasMessageText(value) ? null : t`Write a message`,
        },
    });

    const toRequest = (values: typeof form.values): CrmAutomationRequest => {
        const selection = parseSegmentParam(values.audience === ALL_SUBSCRIBERS ? null : values.audience);
        return {
            name: values.name.trim(),
            trigger,
            subject: values.subject.trim(),
            preheader: values.preheader.trim() || null,
            body: values.body,
            segment_id: selection.kind === 'saved' ? selection.segmentId : null,
            preset: selection.kind === 'preset' ? selection.preset : null,
            delay_days: values.delay_days,
            wait_minutes: Number(values.wait_minutes),
            days_before: values.days_before,
            occurrence_audience: values.occurrence_audience,
        };
    };

    const save = (request: CrmAutomationRequest) => {
        const options = {
            onSuccess: () => {
                showSuccess(automation ? t`Automation saved` : t`Automation created. Turn it on when you're ready.`);
                onClose();
            },
            onError: (error: unknown) => {
                setPendingChanges(null);
                errorHandler(form, error);
            },
        };

        if (automation) {
            updateAutomation.mutate({organizerId, automationId: automation.id, automation: request}, options);
        } else {
            createAutomation.mutate({organizerId, automation: request}, options);
        }
    };

    const handleSubmit = (values: typeof form.values) => {
        const request = toRequest(values);

        if (automation?.is_enabled) {
            setPendingChanges(request);
        } else {
            save(request);
        }
    };

    const audienceOptions = [
        ...(triggersAllowingEveryone.includes(trigger) ? [{group: t`Everyone`, items: [{value: ALL_SUBSCRIBERS, label: t`Everyone who opted in`}]}] : []),
        {
            group: t`Ready-made`,
            items: (segments?.presets ?? [])
                .filter(preset => preset.key !== 'sms_subscribers' && (trigger === 'SEGMENT_ENTERED' || preset.key !== 'subscribers'))
                .map(preset => ({value: `preset:${preset.key}`, label: presetLabel(preset.key)})),
        },
        ...((segments?.data.length ?? 0) > 0 ? [{
            group: t`Your segments`,
            items: segments!.data.map(segment => ({value: `saved:${segment.id}`, label: segment.name})),
        }] : []),
        ...deletedSegmentOptions(form.values.audience, segments),
    ];

    const audienceDescription = (): string => {
        switch (trigger) {
            case 'SEGMENT_ENTERED':
                return t`People already in the segment when you turn this on won't get it. Each person gets it once.`;
            case 'ABANDONED_CHECKOUT':
                return t`Only people who opted in receive it. Narrow it down with a segment if you like.`;
            default:
                return t`Only people who opted in receive it.`;
        }
    };

    return (
        <>
            <Modal opened={!pendingChanges} onClose={onClose} heading={automation ? t`Edit automation` : automationTitle(trigger)} size="xl">
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <TextInput
                        {...form.getInputProps('name')}
                        label={t`Name`}
                        description={t`Only your team sees this`}
                        maxLength={80}
                        data-testid="automation-name"
                    />

                    {trigger === 'AFTER_EVENT' && (
                        <NumberInput
                            {...form.getInputProps('delay_days')}
                            label={t`Days after the event ends`}
                            description={t`Goes to everyone who had a ticket and opted in`}
                            min={0}
                            max={30}
                            allowDecimal={false}
                        />
                    )}

                    {trigger === 'BEFORE_EVENT' && (
                        <>
                            <NumberInput
                                {...form.getInputProps('days_before')}
                                label={t`Days before each date`}
                                description={t`Sent at 10:00 in the event's timezone`}
                                min={1}
                                max={14}
                                allowDecimal={false}
                                data-testid="automation-days-before"
                            />
                            <div className={classes.field}>
                                <Text size="sm" fw={500}>{t`Who gets it`}</Text>
                                <SegmentedControl
                                    fullWidth
                                    value={form.values.occurrence_audience}
                                    onChange={value => form.setFieldValue('occurrence_audience', value as CrmOccurrenceAudience)}
                                    data={[
                                        {value: 'purchased', label: t`Ticket holders`},
                                        {value: 'not_purchased', label: t`Subscribers without a ticket`},
                                    ]}
                                />
                                <Text size="xs" c="dimmed">
                                    {form.values.occurrence_audience === 'purchased'
                                        ? t`Only ticket holders who opted in to marketing get this. To remind every ticket holder, schedule a message from the event's Messages page instead.`
                                        : t`For a last-chance nudge. Each person gets it at most once a week, even for events with many dates.`}
                                </Text>
                            </div>
                        </>
                    )}

                    {trigger === 'ABANDONED_CHECKOUT' && (
                        <Select
                            {...form.getInputProps('wait_minutes')}
                            label={t`Send it`}
                            description={t`Each person gets it once per event. Anyone who books in the meantime is skipped.`}
                            data={WAIT_OPTIONS.map(minutes => {
                                const wait = abandonedWaitLabel(minutes);
                                return {value: String(minutes), label: t`${wait} after they leave`};
                            })}
                            allowDeselect={false}
                            data-testid="automation-wait"
                        />
                    )}

                    {triggersWithAudience.includes(trigger) && (
                        <Select
                            {...form.getInputProps('audience')}
                            label={trigger === 'SEGMENT_ENTERED' ? t`When someone joins` : t`Send to`}
                            description={audienceDescription()}
                            data={audienceOptions}
                            allowDeselect={false}
                        />
                    )}

                    <TextInput
                        {...form.getInputProps('subject')}
                        label={t`Subject line`}
                        maxLength={200}
                        ref={subjectInput}
                        rightSectionWidth={84}
                        rightSectionPointerEvents="all"
                        rightSection={(
                            <InsertTokenMenu
                                tokens={tokens}
                                onInsert={token => form.setFieldValue('subject', insertAtCursor(form.values.subject, token, subjectInput.current))}
                            />
                        )}
                    />
                    <TextInput
                        {...form.getInputProps('preheader')}
                        label={t`Preview text`}
                        description={t`Optional. Shown after the subject in most inboxes.`}
                        maxLength={150}
                        ref={preheaderInput}
                        rightSectionWidth={84}
                        rightSectionPointerEvents="all"
                        rightSection={(
                            <InsertTokenMenu
                                tokens={tokens}
                                onInsert={token => form.setFieldValue('preheader', insertAtCursor(form.values.preheader, token, preheaderInput.current))}
                            />
                        )}
                    />
                    <Editor
                        label={t`Message`}
                        value={form.values.body}
                        onChange={value => form.setFieldValue('body', value)}
                        editorType="simple"
                        size="sm"
                        emailContent
                        error={form.errors.body}
                        additionalToolbarControls={<LiquidTokenMenu tokens={tokens}/>}
                    />
                    <ModalFooter>
                        <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                        <Button type="submit" loading={createAutomation.isPending || updateAutomation.isPending} data-testid="automation-save">
                            {automation?.is_enabled ? t`Review changes` : automation ? t`Save` : t`Create automation`}
                        </Button>
                    </ModalFooter>
                </form>
            </Modal>

            {automation && pendingChanges && (
                <ConfirmAutomationModal
                    organizerId={organizerId}
                    draft={pendingChanges}
                    automationId={automation.id}
                    isRunning
                    heading={t`Save changes to "${automationName}"?`}
                    confirmLabel={t`Save changes`}
                    cancelLabel={t`Back to editing`}
                    timezone={timezone}
                    isConfirming={updateAutomation.isPending}
                    onConfirm={() => save(pendingChanges)}
                    onClose={() => setPendingChanges(null)}
                />
            )}
        </>
    );
};
