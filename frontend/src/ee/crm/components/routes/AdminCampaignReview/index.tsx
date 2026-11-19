import {useState} from "react";
import {plural, t, Trans} from "@lingui/macro";
import {Alert, Button, Container, Group, List, Skeleton, Stack, Table, Text, Textarea, Title} from "@mantine/core";
import {useMediaQuery} from "@mantine/hooks";
import {Modal, ModalFooter} from "../../../../../components/common/Modal";
import {FeatureFlag} from "../../../../../constants/featureFlags.ts";
import {dateToBrowserTz, isDateInFuture, relativeDate} from "../../../../../utilites/dates.ts";
import {firstApiError} from "../../../../../utilites/apiErrors.ts";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";
import {useLicensedFeature} from "../../../../licensing/hooks/useLicensedFeature.ts";
import tableStyles from "../../../../../styles/admin-table.module.scss";
import {useReviewAdminCrmCampaign} from "../../../mutations/useReviewAdminCrmCampaign.ts";
import {useGetAdminCrmCampaigns} from "../../../queries/useGetAdminCrmCampaigns.ts";
import {CrmAdminCampaign} from "../../../types.ts";
import {formatCount} from "../../../utilites/format.ts";
import {presetLabel, reviewReasonLabel} from "../../../utilites/labels.ts";

const audienceLabel = (campaign: CrmAdminCampaign): string => {
    if (campaign.preset) {
        return presetLabel(campaign.preset);
    }

    return campaign.audience_label ?? (campaign.channel === 'SMS' ? t`Everyone who opted in to texts` : t`Everyone who opted in`);
};

const automaticCheckLabel = (campaign: CrmAdminCampaign): string | null => {
    if (campaign.awaiting_automatic_check) {
        return t`Automatic check in progress`;
    }

    return campaign.ai_review ? t`Automatic check didn't approve it` : null;
};

const ReviewFacts = ({campaign}: {campaign: CrmAdminCampaign}) => {
    const isSms = campaign.channel === 'SMS';
    const subject = campaign.subject;
    const audience = audienceLabel(campaign);
    const recipients = formatCount(campaign.recipients_count);
    const organizerName = campaign.organizer_name;
    const accountName = campaign.account_name;
    const accountId = campaign.account_id;
    const importOnly = campaign.import_only_recipients_count;
    const importOnlyLabel = formatCount(importOnly ?? 0);
    const replyTo = campaign.reply_to;
    const eventTitle = campaign.event_title;
    const earlyAccess = (campaign.early_access_tickets ?? []).join(', ');
    const submitted = relativeDate(campaign.updated_at);
    const accountAge = campaign.account_created_at ? relativeDate(campaign.account_created_at) : null;
    const isScheduled = !!campaign.scheduled_at && isDateInFuture(campaign.scheduled_at);
    const scheduledFor = campaign.scheduled_at ? dateToBrowserTz(campaign.scheduled_at, 'UTC') : null;
    const scheduledIn = campaign.scheduled_at ? relativeDate(campaign.scheduled_at) : null;

    return (
        <>
            {!isSms && <Text size="sm"><Trans><b>Subject:</b> {subject}</Trans></Text>}
            <Text size="sm"><Trans><b>Sent by:</b> {organizerName} ({accountName}, account #{accountId})</Trans></Text>
            {!isSms && replyTo && <Text size="sm"><Trans><b>Replies go to:</b> {replyTo}</Trans></Text>}
            <Text size="sm"><Trans><b>Goes to:</b> {audience}</Trans></Text>
            <Text size="sm"><Trans><b>Recipients:</b> {recipients}</Trans></Text>
            {!isSms && importOnly !== null && importOnly > 0 && (
                <Text size="sm"><Trans><b>Opted in through an import only:</b> {importOnlyLabel}</Trans></Text>
            )}
            {eventTitle && <Text size="sm"><Trans><b>Linked event:</b> {eventTitle}</Trans></Text>}
            {earlyAccess && (
                <Text size="sm">
                    <Trans><b>Early access:</b> when sending starts, these tickets are hidden from the public event page and a promo code is created for them: {earlyAccess}</Trans>
                </Text>
            )}
            {accountAge && <Text size="sm"><Trans><b>Account created:</b> {accountAge}</Trans></Text>}
            <Text size="sm"><Trans><b>Submitted:</b> {submitted}</Trans></Text>
            <Text size="sm">
                {isScheduled
                    ? <Trans><b>If approved:</b> scheduled to send {scheduledFor} ({scheduledIn})</Trans>
                    : <Trans><b>If approved:</b> starts sending straight away</Trans>}
            </Text>
        </>
    );
};

const AutomaticCheckFindings = ({campaign}: {campaign: CrmAdminCampaign}) => {
    const review = campaign.ai_review;

    if (campaign.awaiting_automatic_check) {
        return (
            <Alert color="blue" title={t`The automatic check is still running`}>
                {t`If it finds no problems this campaign is approved without you. Refresh in a minute before deciding.`}
            </Alert>
        );
    }

    if (!review || review.decision !== 'NEEDS_PERSON') {
        return null;
    }

    const confidence = Math.round(review.confidence * 100);
    const checked = relativeDate(review.reviewed_at);

    return (
        <Alert color="yellow" title={t`The automatic check didn't approve this campaign`}>
            {review.reasons.length > 0 && (
                <List size="sm" spacing={4}>
                    {review.reasons.map(reason => <List.Item key={reason}>{reason}</List.Item>)}
                </List>
            )}
            <Text size="xs" c="dimmed" mt="xs">
                <Trans>Checked {checked}, {confidence}% confident.</Trans>
            </Text>
        </Alert>
    );
};

const ApprovalLockedNotice = () => (
    <Alert color="orange" title={t`Campaigns can't be approved without a licence`}>
        {t`This installation's licence doesn't include Audience at the moment, so approving is switched off. You can still reject a campaign.`}
    </Alert>
);

const AdminCampaignReview = () => {
    const campaignsQuery = useGetAdminCrmCampaigns();
    const review = useReviewAdminCrmCampaign();
    const {isUnlicensed} = useLicensedFeature(FeatureFlag.CRM);
    const isNarrow = useMediaQuery('(max-width: 48em)');
    const [selected, setSelected] = useState<CrmAdminCampaign | null>(null);
    const [isRejecting, setIsRejecting] = useState(false);
    const [rejectionNote, setRejectionNote] = useState('');
    const campaigns = campaignsQuery.data ?? [];
    const approvesIntoSchedule = !!selected?.scheduled_at && isDateInFuture(selected.scheduled_at);

    const close = () => {
        setSelected(null);
        setIsRejecting(false);
        setRejectionNote('');
    };

    const handleReview = (decision: 'approve' | 'reject') => selected && review.mutate({
        campaign: selected,
        decision,
        note: decision === 'reject' ? rejectionNote.trim() || null : null,
    }, {
        onSuccess: response => {
            showSuccess(response.message);
            close();
        },
        onError: error => showError(firstApiError(error, t`We couldn't save your decision. Please try again.`)),
    });

    const secondLine = (campaign: CrmAdminCampaign): string | null => {
        if (campaign.channel === 'SMS') {
            return t`Text message`;
        }

        return campaign.subject && campaign.subject !== campaign.name ? campaign.subject : null;
    };

    return (
        <Container size="xl" p={isNarrow ? 'md' : 'xl'}>
            <Stack gap="lg">
                <div>
                    <Title order={1}>{t`Audience campaigns`}</Title>
                    <Text c="dimmed" size="sm">{t`Marketing campaigns waiting for approval before they're sent.`}</Text>
                </div>

                {isUnlicensed && campaigns.length > 0 && <ApprovalLockedNotice/>}

                {campaignsQuery.isLoading && <Skeleton height={200} radius="md"/>}

                {campaignsQuery.isError && (
                    <Text c="dimmed">{t`We couldn't load the campaigns waiting for review. Please refresh the page.`}</Text>
                )}

                {campaignsQuery.data && campaigns.length === 0 && (
                    <Text c="dimmed">{t`Nothing is waiting for review.`}</Text>
                )}

                {campaigns.length > 0 && (
                    <div className={tableStyles.tableWrapper}>
                        <Table className={tableStyles.table} highlightOnHover>
                            <Table.Thead>
                                <Table.Tr>
                                    <Table.Th>{t`Campaign`}</Table.Th>
                                    {!isNarrow && (
                                        <>
                                            <Table.Th>{t`Account`}</Table.Th>
                                            <Table.Th>{t`Recipients`}</Table.Th>
                                            <Table.Th>{t`Reason`}</Table.Th>
                                            <Table.Th>{t`Submitted`}</Table.Th>
                                        </>
                                    )}
                                    <Table.Th/>
                                </Table.Tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {campaigns.map(campaign => {
                                    const detail = secondLine(campaign);
                                    const recipients = formatCount(campaign.recipients_count);
                                    const recipientsLabel = plural(campaign.recipients_count, {one: '# recipient', other: '# recipients'});
                                    const accountName = campaign.account_name;
                                    const submitted = relativeDate(campaign.updated_at);

                                    return (
                                        <Table.Tr key={campaign.id}>
                                            <Table.Td>
                                                <Text fw={600} size="sm">{campaign.name}</Text>
                                                {detail && <Text size="xs" c="dimmed">{detail}</Text>}
                                                {isNarrow && (
                                                    <Text size="xs" c="dimmed">
                                                        {[accountName, recipientsLabel, submitted].filter(Boolean).join(' · ')}
                                                    </Text>
                                                )}
                                            </Table.Td>
                                            {!isNarrow && (
                                                <>
                                                    <Table.Td>
                                                        <Text size="sm">{campaign.account_name} (#{campaign.account_id})</Text>
                                                        <Text size="xs" c="dimmed">{campaign.organizer_name}</Text>
                                                    </Table.Td>
                                                    <Table.Td>{recipients}</Table.Td>
                                                    <Table.Td>
                                                        {reviewReasonLabel(campaign.status_reason)}
                                                        <Text size="xs" c="dimmed">{automaticCheckLabel(campaign)}</Text>
                                                    </Table.Td>
                                                    <Table.Td>{submitted}</Table.Td>
                                                </>
                                            )}
                                            <Table.Td>
                                                <Button size="sm" variant="default" onClick={() => setSelected(campaign)}>
                                                    {t`Review`}
                                                </Button>
                                            </Table.Td>
                                        </Table.Tr>
                                    );
                                })}
                            </Table.Tbody>
                        </Table>
                    </div>
                )}
            </Stack>

            {selected && (
                <Modal opened onClose={close} heading={selected.name} size="xl">
                    <Stack gap="xs">
                        {isUnlicensed && <ApprovalLockedNotice/>}
                        <AutomaticCheckFindings campaign={selected}/>
                        <ReviewFacts campaign={selected}/>
                        {isRejecting ? (
                            <Textarea
                                label={t`Reason for the organizer (optional)`}
                                description={t`Emailed to the organizer and shown on their campaign. It won't be sent, and they can edit and resubmit it.`}
                                value={rejectionNote}
                                onChange={event => setRejectionNote(event.currentTarget.value)}
                                maxLength={500}
                                autosize
                                minRows={3}
                                data-autofocus
                            />
                        ) : selected.channel === 'SMS' ? (
                            <Text size="sm" style={{whiteSpace: 'pre-wrap', padding: 12, border: '1px solid var(--mantine-color-gray-3)', borderRadius: 8}}>
                                {selected.preview_text}
                            </Text>
                        ) : (
                            <iframe
                                title={t`Email preview`}
                                sandbox=""
                                srcDoc={selected.preview_html ?? ''}
                                style={{width: '100%', height: '50vh', border: '1px solid var(--mantine-color-gray-3)', borderRadius: 8}}
                            />
                        )}
                    </Stack>
                    {isRejecting ? (
                        <ModalFooter>
                            <Group>
                                <Button variant="default" onClick={() => setIsRejecting(false)}>{t`Back`}</Button>
                                <Button color="red" onClick={() => handleReview('reject')} loading={review.isPending}>
                                    {t`Reject campaign`}
                                </Button>
                            </Group>
                        </ModalFooter>
                    ) : (
                        <ModalFooter
                            start={(
                                <Button color="red" variant="light" onClick={() => setIsRejecting(true)}>
                                    {t`Reject`}
                                </Button>
                            )}
                        >
                            <Group>
                                <Button variant="default" onClick={close}>{t`Cancel`}</Button>
                                <Button onClick={() => handleReview('approve')} loading={review.isPending} disabled={isUnlicensed}>
                                    {approvesIntoSchedule ? t`Approve and schedule` : t`Approve and send`}
                                </Button>
                            </Group>
                        </ModalFooter>
                    )}
                </Modal>
            )}
        </Container>
    );
};

export default AdminCampaignReview;
