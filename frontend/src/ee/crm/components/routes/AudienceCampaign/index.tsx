import {useEffect, useMemo, useRef, useState} from "react";
import {Navigate, useNavigate, useParams, useSearchParams} from "react-router";
import {plural, t, Trans} from "@lingui/macro";
import {SegmentedControl, Select, Skeleton, Textarea, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useDebouncedValue} from "@mantine/hooks";
import {useQueryClient} from "@tanstack/react-query";
import {Editor} from "../../../../../components/common/Editor";
import {useAccess} from "../../../../../hooks/useAccess.ts";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUnsavedChangesGuard} from "../../../../../hooks/useUnsavedChangesGuard.ts";
import {useGetMe} from "../../../../../queries/useGetMe.ts";
import {useGetOrganizer} from "../../../../../queries/useGetOrganizer.ts";
import {firstApiError} from "../../../../../utilites/apiErrors.ts";
import {confirmationDialog} from "../../../../../utilites/confirmationDialog.tsx";
import {showError, showInfo, showSuccess} from "../../../../../utilites/notifications.tsx";
import {useCrmAccess} from "../../../hooks/useCrmAccess.ts";
import {useCrmReady} from "../../../hooks/useCrmReady.ts";
import {useTextMessagesVisible} from "../../../hooks/useTextMessagesVisible.ts";
import {useCancelCrmCampaign} from "../../../mutations/useCancelCrmCampaign.ts";
import {useCreateCrmCampaign} from "../../../mutations/useCreateCrmCampaign.ts";
import {useDeleteCrmCampaign} from "../../../mutations/useDeleteCrmCampaign.ts";
import {useSendTestCrmCampaign} from "../../../mutations/useSendTestCrmCampaign.ts";
import {useUpdateCrmCampaign} from "../../../mutations/useUpdateCrmCampaign.ts";
import {useDuplicateCrmCampaign} from "../../../mutations/useCrmCampaignTemplateMutations.ts";
import {useGetCrmCampaignTemplates} from "../../../queries/useGetCrmCampaignTemplates.ts";
import {crmKeys} from "../../../queries/crmQueryKeys.ts";
import {useGetCrmCampaign} from "../../../queries/useGetCrmCampaign.ts";
import {useGetCrmCampaignPreview} from "../../../queries/useGetCrmCampaignPreview.ts";
import {useGetCrmCampaignDraftPreview} from "../../../queries/useGetCrmCampaignDraftPreview.ts";
import {useGetCrmSegmentCount} from "../../../queries/useGetCrmSegmentCount.ts";
import {useGetCrmSegmentOptions} from "../../../queries/useGetCrmSegmentOptions.ts";
import {useGetCrmSegments} from "../../../queries/useGetCrmSegments.ts";
import {useGetCrmSentSmsCampaignPreview} from "../../../queries/useGetCrmSentSmsCampaignPreview.ts";
import {useGetCrmSmsCampaignPreview} from "../../../queries/useGetCrmSmsCampaignPreview.ts";
import {useGetCrmSmsConnection} from "../../../queries/useGetCrmSmsConnection.ts";
import {CrmCampaign} from "../../../types.ts";
import {formatCount, parseSegmentParam, selectionToReference} from "../../../utilites/format.ts";
import {presetLabel} from "../../../utilites/labels.ts";
import {hasMessageText, lostImages} from "../../../utilites/messageBody.ts";
import {LiquidTokenMenu} from "../../../../../components/common/Editor/Controls/LiquidTokenControl";
import {campaignTokens, insertAtCursor} from "../../../utilites/tokens.ts";
import {InsertTokenMenu} from "../../InsertTokenMenu";
import {AudienceShell} from "../../AudienceShell";
import {CampaignActionBar} from "./CampaignActionBar.tsx";
import {CampaignNotices} from "./CampaignNotices.tsx";
import {CampaignFormValues, campaignToFormValues, CUSTOM_AUDIENCE, initialAudience, toRequest} from "./campaignForm.ts";
import {audienceOptions, eventOptions} from "./campaignOptions.ts";
import {EarlyAccessTickets, SentEarlyAccess} from "./EarlyAccessTickets.tsx";
import {PreviewPane} from "./PreviewPane.tsx";
import {Report} from "./Report.tsx";
import {SendModal} from "./SendModal.tsx";
import {SmsPreviewPane, typedSmsVariants} from "./SmsPreviewPane.tsx";
import {SmsTestModal} from "./SmsTestModal.tsx";
import {smsCountryList} from "../../../utilites/smsCountries.ts";
import {SaveTemplateModal} from "../AudienceTemplates/SaveTemplateModal.tsx";
import classes from "./Campaign.module.scss";

const STOPPABLE_STATUSES = ['SCHEDULED', 'PENDING_REVIEW', 'SENDING'];

const AudienceCampaign = () => {
    const {organizerId, campaignId} = useParams();
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const {can} = useAccess();
    const {data: me} = useGetMe();
    const {canChange, canRemove, isLocked} = useCrmAccess();
    const isReady = useCrmReady(organizerId);
    const isTextMessagesVisible = useTextMessagesVisible();
    const isNew = campaignId === 'new';
    const isSmsRef = useRef(false);
    const basePath = `/manage/organizer/${organizerId}/audience/campaigns`;
    const {data: organizer} = useGetOrganizer(organizerId);
    const campaignQuery = useGetCrmCampaign(organizerId, isNew ? undefined : campaignId);
    const campaign = campaignQuery.data;
    const segmentsQuery = useGetCrmSegments(organizerId, isReady);
    const optionsQuery = useGetCrmSegmentOptions(organizerId, {}, isReady);
    const createCampaign = useCreateCrmCampaign();
    const updateCampaign = useUpdateCrmCampaign();
    const deleteCampaign = useDeleteCrmCampaign();
    const cancelCampaign = useCancelCrmCampaign();
    const sendTest = useSendTestCrmCampaign();
    const errorHandler = useFormErrorResponseHandler();
    const hydratedId = useRef<number | null>(null);
    const [sendModal, setSendModal] = useState<'now' | 'later' | null>(null);
    const [editorRevision, setEditorRevision] = useState(0);
    const [mode, setMode] = useState<'write' | 'preview'>('write');
    const [previewContactId, setPreviewContactId] = useState<number | null>(null);
    const [isSavingTemplate, setSavingTemplate] = useState(false);
    const subjectInput = useRef<HTMLInputElement>(null);
    const preheaderInput = useRef<HTMLInputElement>(null);
    const duplicateCampaign = useDuplicateCrmCampaign();
    const templateParam = searchParams.get('template');
    const starterParam = searchParams.get('starter');
    const templatesQuery = useGetCrmCampaignTemplates(organizerId, isNew && !!(templateParam || starterParam));
    const prefilledFrom = useRef<string | null>(null);
    const templateId = useRef<number | undefined>(undefined);
    const reloadEditor = () => setEditorRevision(revision => revision + 1);

    const form = useForm<CampaignFormValues>({
        initialValues: {
            name: '',
            subject: '',
            preheader: '',
            body: '',
            audience: initialAudience(searchParams.get('segment')),
            event_id: null,
            early_access_product_ids: [],
        },
        validate: {
            subject: value => isSmsRef.current || value.trim() ? null : t`Add a subject line`,
            name: value => !isSmsRef.current || value.trim() ? null : t`Give this text message a name so you can find it later`,
        },
    });

    useEffect(() => {
        if (campaign && campaign.id !== hydratedId.current) {
            hydratedId.current = campaign.id;
            form.setValues(campaignToFormValues(campaign));
            form.resetDirty();
            reloadEditor();
        }
    }, [campaign]);

    useEffect(() => {
        const source = templateParam ? `template:${templateParam}` : starterParam ? `starter:${starterParam}` : null;
        const templates = templatesQuery.data;
        if (!isNew || !source || !templates || prefilledFrom.current === source) {
            return;
        }
        prefilledFrom.current = source;
        const template = templateParam ? templates.data.find(item => String(item.id) === templateParam) : undefined;
        const starter = starterParam ? templates.starters.find(item => item.key === starterParam) : undefined;
        const content = template ?? starter;
        if (!content) {
            return;
        }
        templateId.current = template?.id;
        form.setValues({subject: content.subject ?? '', preheader: content.preheader ?? '', body: content.body});
        form.resetDirty();
        reloadEditor();
    }, [templatesQuery.data, templateParam, starterParam, isNew]);

    const isEditable = canChange && (isNew || !!campaign?.is_editable);
    const isSms = isNew ? searchParams.get('channel') === 'SMS' : campaign?.channel === 'SMS';
    isSmsRef.current = isSms;
    const smsBodyInput = useRef<HTMLTextAreaElement>(null);
    const [isTestingSms, setTestingSms] = useState(false);

    useUnsavedChangesGuard(
        () => isEditable && form.isDirty(),
        t`Leave without saving? Your campaign changes will be lost.`,
    );

    const canDelete = canRemove && !!campaign?.is_editable;
    const isScheduled = campaign?.status === 'SCHEDULED';
    const isPausedByLicence = isLocked && isScheduled;
    const isRestricted = !!campaign?.has_restricted_rules;
    const isCountedAtSend = campaign?.status === 'SCHEDULED' && campaign.recipients_count === 0;
    const isCustomAudience = form.values.audience === CUSTOM_AUDIENCE;
    const selection = parseSegmentParam(form.values.audience === 'all' || isCustomAudience ? null : form.values.audience);
    const countQuery = useGetCrmSegmentCount(
        organizerId,
        isCustomAudience && campaign?.definition ? {definition: campaign.definition} : selectionToReference(selection) ?? {},
        isReady && (isEditable || isCountedAtSend) && !isRestricted,
    );
    const [draftSubject] = useDebouncedValue(form.values.subject, 600);
    const [draftBody] = useDebouncedValue(form.values.body, 600);
    const [draftPreheader] = useDebouncedValue(form.values.preheader, 600);
    const previewDraft = useMemo(() => ({
        subject: draftSubject || null,
        preheader: draftPreheader || null,
        body: draftBody || null,
        event_id: form.values.event_id ? Number(form.values.event_id) : null,
        early_access_product_ids: form.values.event_id ? form.values.early_access_product_ids.map(Number) : [],
        contact_id: previewContactId,
    }), [draftSubject, draftPreheader, draftBody, form.values.event_id, form.values.early_access_product_ids, previewContactId]);
    const tokens = campaignTokens(!!form.values.event_id);
    const draftPreviewQuery = useGetCrmCampaignDraftPreview(organizerId, previewDraft, isEditable && !isSms);
    const savedPreviewQuery = useGetCrmCampaignPreview(organizerId, campaign?.id, !isEditable && !isSms);
    const previewQuery = isEditable ? draftPreviewQuery : savedPreviewQuery;
    const smsConnectionQuery = useGetCrmSmsConnection(organizerId, isReady && isSms);
    const smsDraft = useMemo(() => ({
        body: draftBody || null,
        event_id: previewDraft.event_id,
        early_access_product_ids: previewDraft.early_access_product_ids,
    }), [draftBody, previewDraft.event_id, previewDraft.early_access_product_ids]);
    const draftSmsPreviewQuery = useGetCrmSmsCampaignPreview(organizerId, smsDraft, isReady && isSms && isEditable && draftBody.trim() !== '');
    const savedSmsPreviewQuery = useGetCrmSentSmsCampaignPreview(organizerId, campaign?.id, isReady && isSms && !isEditable);
    const smsPreviewQuery = isEditable ? draftSmsPreviewQuery : savedSmsPreviewQuery;
    const isSaving = createCampaign.isPending || updateCampaign.isPending;
    const timezone = organizer?.timezone ?? 'UTC';
    const mailable = countQuery.data?.mailable_count ?? 0;
    const textable = countQuery.data?.textable_count ?? 0;
    const mailableLabel = formatCount(mailable);
    const textableLabel = formatCount(textable);
    const contactsLabel = formatCount(countQuery.data?.contacts_count ?? 0);
    const reachableByText = countQuery.data?.textable_in_enabled_countries_count ?? 0;

    if (isNew && me && !canChange) {
        return <Navigate to={basePath} replace/>;
    }

    const save = (values: CampaignFormValues, after?: (saved: CrmCampaign) => void) => {
        const options = {
            onSuccess: (response: { data: CrmCampaign }) => {
                const savedBody = response.data.body ?? '';
                if (lostImages(values.body, savedBody)) {
                    showInfo(t`Images from other sites can't be used, so we removed them. Upload them instead.`);
                    form.setFieldValue('body', savedBody);
                    reloadEditor();
                    values = {...values, body: savedBody};
                }
                form.resetDirty(values);
                if (isNew) {
                    hydratedId.current = response.data.id;
                    queryClient.setQueryData(crmKeys.campaign(organizerId, response.data.id), response.data);
                    navigate(`${basePath}/${response.data.id}`, {replace: true});
                }
                if (after) {
                    after(response.data);
                } else {
                    showSuccess(t`Draft saved`);
                }
            },
            onError: (error: unknown) => errorHandler(form, error),
        };

        if (isNew || !campaign) {
            createCampaign.mutate({organizerId, campaign: toRequest(values, null, isSms ? undefined : templateId.current, isSms ? 'SMS' : 'EMAIL')}, options);
        } else {
            updateCampaign.mutate({organizerId, campaignId: campaign.id, campaign: toRequest(values, campaign.definition)}, options);
        }
    };

    const saveThen = (after: (saved: CrmCampaign) => void) => {
        if (form.validate().hasErrors) {
            setMode('write');
            return;
        }
        if (campaign && !form.isDirty()) {
            after(campaign);
            return;
        }
        save(form.values, after);
    };

    const openSendModal = (when: 'now' | 'later') => {
        if (!hasMessageText(form.values.body)) {
            form.setErrors({...form.validate().errors, body: t`Write a message before sending`});
            setMode('write');
            return;
        }
        saveThen(() => setSendModal(when));
    };

    const handleTest = () => isSms
        ? saveThen(() => setTestingSms(true))
        : saveThen(saved => sendTest.mutate({organizerId, campaignId: saved.id}, {
            onSuccess: response => showSuccess(response.message),
            onError: error => showError(firstApiError(error, t`We couldn't send the test email. Please try again.`)),
        }));

    const sendSmsTest = (phone: string) => campaign && sendTest.mutate({organizerId, campaignId: campaign.id, phone}, {
        onSuccess: response => {
            setTestingSms(false);
            showSuccess(response.message);
        },
        onError: error => showError(firstApiError(error, t`We couldn't send the test text. Please try again.`)),
    });

    const handleDelete = () => campaign && confirmationDialog(
        t`Delete this draft?`,
        () => deleteCampaign.mutate({organizerId, campaignId: campaign.id}, {
            onSuccess: () => {
                form.resetDirty();
                showSuccess(t`Campaign deleted`);
                navigate(basePath);
            },
            onError: error => showError(firstApiError(error, t`We couldn't delete this campaign. Please try again.`)),
        }),
        {confirm: t`Delete draft`, destructive: true, description: t`This can't be undone.`},
    );

    const handleDuplicate = () => campaign && duplicateCampaign.mutate({organizerId, campaignId: campaign.id}, {
        onSuccess: ({data, warnings}) => {
            showSuccess(t`Campaign duplicated`);
            warnings.forEach(warning => showInfo(warning));
            navigate(`${basePath}/${data.id}`);
        },
        onError: error => showError(firstApiError(error, t`We couldn't duplicate this campaign. Please try again.`)),
    });

    const cancel = (current: CrmCampaign, successMessage: string) => cancelCampaign.mutate({organizerId, campaignId: current.id}, {
        onSuccess: () => showSuccess(successMessage),
        onError: error => showError(firstApiError(error, t`We couldn't stop this campaign. Please try again.`)),
    });

    const handleCancel = () => {
        if (!campaign) {
            return;
        }
        if (campaign.status !== 'SENDING' && campaign.automation_id) {
            confirmationDialog(
                t`Cancel this automated email?`,
                () => cancel(campaign, t`Email cancelled`),
                {
                    confirm: t`Cancel this email`,
                    cancel: t`Keep it`,
                    destructive: true,
                    description: t`The people it was going to won't get this email, and this can't be undone. The automation stays on and keeps sending later emails.`,
                },
            );
            return;
        }
        if (campaign.status !== 'SENDING') {
            cancel(campaign, t`Moved back to draft`);
            return;
        }
        const sentLabel = formatCount(campaign.stats.sent);
        const totalLabel = formatCount(campaign.recipients_count);
        confirmationDialog(
            t`Stop sending this campaign?`,
            () => cancel(campaign, t`Campaign stopped`),
            {
                confirm: t`Stop sending`,
                destructive: true,
                description: isSms
                    ? t`${sentLabel} of ${totalLabel} texts have already gone out. The rest won't be sent, and this can't be undone.`
                    : t`${sentLabel} of ${totalLabel} emails have already gone out. The rest won't be sent, and this can't be undone.`,
            },
        );
    };

    const organizerName = organizer?.name ?? '';
    const fromName = previewQuery.data?.from_name ?? '';
    const replyTo = previewQuery.data?.reply_to;
    const renderedSms = smsPreviewQuery.data?.variants;
    const isSmsRendered = !!renderedSms?.length && (!isEditable || (draftBody === form.values.body && !draftSmsPreviewQuery.isPlaceholderData));
    const smsCountries = smsConnectionQuery.data?.enabled_countries ?? [];
    const smsVariants = isSmsRendered ? renderedSms : typedSmsVariants(organizerName, form.values.body, smsCountries);
    const smsBubbles = isSmsRendered || form.values.body ? smsVariants : typedSmsVariants(organizerName, t`Your message`, smsCountries);
    const smsParts = Math.max(...smsVariants.map(variant => variant.parts));
    const hasSmsPlaceholders = isSmsRendered ? !!smsPreviewQuery.data?.uses_placeholders : form.values.body.includes('{{');
    const title = campaign ? campaign.subject || campaign.name : (isSms ? t`New text message` : t`New email campaign`);
    const titleNote = campaign?.subject && campaign.name !== campaign.subject ? campaign.name : null;
    const scheduledCount = isSms ? reachableByText : mailable;
    const sentAudience = campaign
        ? (campaign.preset ? presetLabel(campaign.preset) : campaign.segment_name ?? (isSms ? t`Everyone who opted in to texts` : t`Everyone who opted in`))
        : '';
    const canStop = canRemove && !!campaign && STOPPABLE_STATUSES.includes(campaign.status);
    const isWaitingForItsTime = campaign?.status === 'SCHEDULED' || campaign?.status === 'PENDING_REVIEW';
    const statusDate = campaign?.sent_at ?? (isWaitingForItsTime && !isPausedByLicence ? campaign?.scheduled_at : null);
    const stopLabel = campaign?.status === 'SENDING'
        ? t`Stop sending`
        : campaign?.automation_id ? t`Cancel this email` : t`Move back to draft`;

    return (
        <AudienceShell isTextCampaign={isSms}>
            <CampaignActionBar
                basePath={basePath}
                campaign={campaign}
                isNew={isNew}
                statusDate={statusDate}
                timezone={timezone}
                canCopy={canChange && !!campaign && (!isSms || isTextMessagesVisible)}
                canDelete={canDelete}
                canStop={canStop}
                isEditable={isEditable}
                isSaving={isSaving}
                isTesting={sendTest.isPending}
                isStopping={cancelCampaign.isPending}
                onDuplicate={handleDuplicate}
                onSaveTemplate={isSms ? undefined : () => setSavingTemplate(true)}
                testLabel={isSms ? t`Send me a test text` : t`Send me a test`}
                onDelete={handleDelete}
                onTest={handleTest}
                onSave={() => form.validate().hasErrors ? setMode('write') : save(form.values)}
                onSend={openSendModal}
                onStop={handleCancel}
                stopLabel={stopLabel}
            />

            {!isNew && campaignQuery.isLoading && <Skeleton height={420} radius="lg"/>}

            {(isNew || campaign) && (
                <div className={classes.heading} data-testid="campaign-title">
                    <h2>{title}</h2>
                    {titleNote && <span>{titleNote}</span>}
                </div>
            )}

            <CampaignNotices
                organizerId={organizerId}
                campaign={campaign}
                isSms={isSms}
                timezone={timezone}
                loadError={campaignQuery.isError ? campaignQuery.error : null}
                isPausedByLicence={isPausedByLicence}
                isRestricted={isRestricted}
                isEditable={isEditable}
            />

            {(isNew || campaign) && (
                <>
                    <SegmentedControl
                        className={classes.modeSwitch}
                        fullWidth
                        value={mode}
                        onChange={value => setMode(value === 'preview' ? 'preview' : 'write')}
                        data={[{value: 'write', label: isEditable ? t`Write` : t`Details`}, {value: 'preview', label: t`Preview`}]}
                    />

                    <div className={classes.workspace}>
                        <div className={classes.compose} data-hidden={mode === 'preview' || undefined} data-report={campaign?.sent_at ? true : undefined}>
                            <div className={classes.line}>
                                <span className={classes.lineLabel}>{t`From`}</span>
                                <span className={classes.fromValue} data-testid="campaign-from">
                                    {isSms ? organizerName : fromName}
                                    {isSms
                                        ? <small>{t`Sent from your Twilio Messaging Service. Every text starts with your organizer name.`}</small>
                                        : (
                                            <small>
                                                {replyTo
                                                    ? t`Sent on behalf of ${organizerName}. Replies go to ${replyTo}.`
                                                    : t`Sent on behalf of ${organizerName}.`}
                                            </small>
                                        )}
                                </span>
                            </div>
                            <div className={classes.line}>
                                <span className={classes.lineLabel}>{t`To`}</span>
                                {isEditable ? (
                                    <div className={classes.lineValue}>
                                        <Select
                                            {...form.getInputProps('audience')}
                                            aria-label={t`Send to`}
                                            data={audienceOptions(campaign, segmentsQuery.data, form.values.audience, isSms)}
                                            allowDeselect={false}
                                            disabled={isRestricted}
                                        />
                                        <span className={classes.lineHint} data-testid="campaign-recipient-count">
                                            {countQuery.isError && t`We couldn't count these people. They're counted again before you send.`}
                                            {countQuery.data && !isSms && <Trans><b>{mailableLabel}</b> of {contactsLabel} opted in</Trans>}
                                            {countQuery.data && isSms && reachableByText === textable && (
                                                <Trans><b>{textableLabel}</b> of {contactsLabel} opted in to texts</Trans>
                                            )}
                                            {countQuery.data && isSms && reachableByText !== textable && (
                                                <>
                                                    <Trans><b>{textableLabel}</b> of {contactsLabel} opted in to texts.</Trans>
                                                    {' '}
                                                    {plural(reachableByText, {
                                                        one: '# of them is in a country you send to.',
                                                        other: '# of them are in countries you send to.',
                                                    })}
                                                </>
                                            )}
                                        </span>
                                    </div>
                                ) : (
                                    <span className={classes.fromValue} data-testid="campaign-recipient-count">
                                        {sentAudience}
                                        {campaign && !isCountedAtSend && !campaign.is_editable && (
                                            <small>{plural(campaign.recipients_count, {one: '# recipient', other: '# recipients'})}</small>
                                        )}
                                        {isCountedAtSend && (
                                            <small>
                                                {countQuery.data
                                                    ? plural(scheduledCount, {
                                                        one: 'About # person right now. Counted again when it sends.',
                                                        other: 'About # people right now. Counted again when it sends.',
                                                    })
                                                    : t`Counted when it sends`}
                                            </small>
                                        )}
                                    </span>
                                )}
                            </div>
                            {!isSms && <div className={`${classes.line} ${classes.subjectLine}`} data-invalid={form.errors.subject ? true : undefined}>
                                <span className={classes.lineLabel}>{t`Subject`}</span>
                                {isEditable ? (
                                    <TextInput
                                        {...form.getInputProps('subject')}
                                        errorProps={{id: 'campaign-subject-error', role: 'alert', className: classes.lineError}}
                                        aria-label={t`Subject line`}
                                        placeholder={t`First dibs on our next night`}
                                        maxLength={200}
                                        data-testid="campaign-subject"
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
                                ) : <span className={classes.fromValue}>{campaign?.subject}</span>}
                            </div>}
                            {!isSms && (isEditable || campaign?.preheader) && (
                                <div className={classes.line} data-invalid={form.errors.preheader ? true : undefined}>
                                    <span className={classes.lineLabel}>{t`Preview text`}</span>
                                    {isEditable ? (
                                        <TextInput
                                            {...form.getInputProps('preheader')}
                                            errorProps={{id: 'campaign-preheader-error', role: 'alert', className: classes.lineError}}
                                            aria-label={t`Preview text`}
                                            placeholder={t`Optional. Shows after the subject.`}
                                            maxLength={150}
                                            data-testid="campaign-preheader"
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
                                    ) : <span className={classes.fromValue}>{campaign?.preheader}</span>}
                                </div>
                            )}
                            {(isEditable || campaign?.event_title) && (
                                <div className={classes.line} data-multiline={form.values.event_id ? true : undefined}>
                                    <span className={classes.lineLabel}>{t`Event`}</span>
                                    {isEditable ? (
                                        <div className={classes.eventValue}>
                                            <Select
                                                {...form.getInputProps('event_id')}
                                                onChange={value => {
                                                    form.setFieldValue('event_id', value);
                                                    form.setFieldValue('early_access_product_ids', []);
                                                }}
                                                aria-label={t`Linked event`}
                                                placeholder={isSms ? t`Optional. Adds an event link.` : t`Optional. Adds an event card.`}
                                                data={eventOptions(campaign, optionsQuery.data, form.values.event_id)}
                                                clearable
                                                searchable
                                                data-testid="campaign-event"
                                            />
                                            {form.values.event_id && (
                                                <EarlyAccessTickets
                                                    needsEventLink={isSms && !form.values.body.includes('event_url')}
                                                    eventId={form.values.event_id}
                                                    value={form.values.early_access_product_ids}
                                                    onChange={ids => form.setFieldValue('early_access_product_ids', ids)}
                                                    error={form.errors.early_access_product_ids}
                                                />
                                            )}
                                        </div>
                                    ) : (
                                        <div className={classes.lineValue}>
                                            <span className={classes.fromValue}>{campaign?.event_title}</span>
                                            {campaign && campaign.event_id && campaign.early_access_product_ids.length > 0 && (
                                                <SentEarlyAccess organizerId={organizerId} campaign={campaign} canRelease={canRemove}/>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )}
                            {campaign && !!campaign.sent_at && (
                                <Report organizerId={organizerId} campaign={campaign} showRevenue={can('VIEW_FINANCIALS')}/>
                            )}
                            {isEditable && (
                                <>
                                    <div className={classes.line} data-invalid={form.errors.name ? true : undefined}>
                                        <span className={classes.lineLabel}>{t`Name`}</span>
                                        <TextInput
                                            {...form.getInputProps('name')}
                                            errorProps={{id: 'campaign-name-error', role: 'alert', className: classes.lineError}}
                                            aria-label={t`Campaign name`}
                                            placeholder={isSms ? t`Only your team sees this.` : t`Optional. Only your team sees this.`}
                                            maxLength={150}
                                            data-testid="campaign-name"
                                        />
                                    </div>
                                    {isSms ? (
                                        <div className={classes.body}>
                                            <Textarea
                                                {...form.getInputProps('body')}
                                                ref={smsBodyInput}
                                                aria-label={t`Message`}
                                                placeholder={t`Write your text message…`}
                                                autosize
                                                minRows={5}
                                                maxLength={1000}
                                                data-testid="campaign-sms-body"
                                            />
                                            <div className={classes.smsCounter} data-warning={smsParts > 2 || undefined}>
                                                <span className={classes.smsCounts} data-testid="campaign-sms-count">
                                                    {smsVariants.map(variant => (
                                                        <span key={variant.countries.join('-')}>
                                                            {smsVariants.length > 1 && `${smsCountryList(variant.countries)}: `}
                                                            {plural(variant.characters, {one: '# character', other: '# characters'})}
                                                            {' · '}
                                                            {plural(variant.parts, {one: '# part', other: '# parts'})}
                                                        </span>
                                                    ))}
                                                </span>
                                                <InsertTokenMenu
                                                    tokens={tokens}
                                                    onInsert={token => form.setFieldValue('body', insertAtCursor(form.values.body, token, smsBodyInput.current))}
                                                />
                                            </div>
                                            <p className={classes.smsHint}>
                                                {[
                                                    t`The count includes your organizer name and the opt-out line. Twilio bills each part for each person.`,
                                                    t`The opt-out line is sent in each person's language, so a text can take more parts for people whose language doesn't use the Latin alphabet.`,
                                                    ...(hasSmsPlaceholders ? [isSmsRendered
                                                        ? t`Placeholders are counted with your own details, so a longer name or link can add a part for some people.`
                                                        : t`Placeholders are counted as typed until the preview updates, so treat this as an estimate.`] : []),
                                                    ...(smsVariants.some(variant => variant.is_unicode)
                                                        ? [t`Emoji or special characters make each part shorter.`]
                                                        : []),
                                                ].join(' ')}
                                            </p>
                                        </div>
                                    ) : <div className={classes.body}>
                                        <Editor
                                            key={editorRevision}
                                            ariaLabel={t`Message`}
                                            placeholder={t`Write your message…`}
                                            value={form.values.body}
                                            onChange={value => form.setFieldValue('body', value)}
                                            editorType="simple"
                                            size="sm"
                                            error={form.errors.body}
                                            dataTestId="campaign-body"
                                            emailContent
                                            additionalToolbarControls={<LiquidTokenMenu tokens={tokens}/>}
                                        />
                                    </div>}
                                </>
                            )}
                        </div>

                        {isSms ? (
                            <SmsPreviewPane
                                variants={smsBubbles}
                                isRendered={isSmsRendered}
                                isSent={!!campaign?.sent_at}
                                isHidden={mode === 'write'}
                                unavailable={isEditable || isSmsRendered ? null : savedSmsPreviewQuery.isError ? 'error' : 'loading'}
                            />
                        ) : <PreviewPane
                            organizerId={organizerId}
                            isEditable={isEditable}
                            isSent={!!campaign?.sent_at}
                            isHidden={mode === 'write'}
                            previewContactId={previewContactId}
                            onPreviewContactChange={setPreviewContactId}
                            preheader={previewQuery.data?.preheader ?? null}
                            subject={previewQuery.data?.subject ?? form.values.subject}
                            fromName={fromName}
                            html={previewQuery.data?.html}
                            isError={previewQuery.isError}
                        />}
                    </div>
                </>
            )}

            {isSavingTemplate && (
                <SaveTemplateModal
                    organizerId={organizerId}
                    content={{subject: form.values.subject, preheader: form.values.preheader, body: form.values.body}}
                    onClose={() => setSavingTemplate(false)}
                />
            )}

            {isTestingSms && (
                <SmsTestModal
                    defaultCountry={smsConnectionQuery.data?.enabled_countries[0]}
                    isSending={sendTest.isPending}
                    onSend={sendSmsTest}
                    onClose={() => setTestingSms(false)}
                />
            )}

            {sendModal && campaign && (
                <SendModal
                    organizerId={organizerId}
                    campaign={campaign}
                    timezone={timezone}
                    isScheduling={sendModal === 'later'}
                    estimatedSmsParts={smsParts}
                    onClose={() => setSendModal(null)}
                />
            )}
        </AudienceShell>
    );
};

export default AudienceCampaign;
