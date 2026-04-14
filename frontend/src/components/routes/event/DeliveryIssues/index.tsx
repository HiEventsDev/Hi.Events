import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {PageBody} from "../../../common/PageBody";
import {ActionIcon, Alert, Badge, Button, Group, Switch, Table, Text, Tooltip} from "@mantine/core";
import {Card} from "../../../common/Card";
import {useParams} from "react-router";
import {useGetDeliveryIssues} from "../../../../queries/useGetDeliveryIssues.ts";
import {relativeDate} from "../../../../utilites/dates.ts";
import {Pagination} from "../../../common/Pagination";
import {useState} from "react";
import {TableSkeleton} from "../../../common/TableSkeleton";
import {IconCheck, IconCircleDashed} from "@tabler/icons-react";
import {useResolveDeliveryIssue} from "../../../../mutations/useResolveDeliveryIssue.ts";
import {ResolveDeliveryIssueModal} from "../../../modals/ResolveDeliveryIssueModal";
import {DeliveryIssue} from "../../../../types.ts";
import {showError} from "../../../../utilites/notifications.tsx";
import {statusColor, emailTypeLabel} from "../MessageTracking/shared.tsx";
import classes from "./DeliveryIssues.module.scss";

const DeliveryIssues = () => {
    const {eventId} = useParams();
    const [page, setPage] = useState(1);
    const [showResolved, setShowResolved] = useState(false);
    const [resolveModalMessage, setResolveModalMessage] = useState<DeliveryIssue | null>(null);
    const issuesQuery = useGetDeliveryIssues(eventId, {pageNumber: page, perPage: 20}, showResolved);
    const issues = issuesQuery.data?.data;
    const pagination = issuesQuery.data?.meta;
    const resolveMutation = useResolveDeliveryIssue();

    const handleToggleResolved = (issue: DeliveryIssue) => {
        resolveMutation.mutate({
            eventId: eventId!,
            messageId: issue.id!,
            sourceType: issue.source_type,
        }, {
            onError: () => showError(t`Failed to update resolved status.`),
        });
    };

    const sourceTypeLabel = (issue: DeliveryIssue) => {
        const label = issue.source_type === 'announcement'
            ? t`Announcement`
            : emailTypeLabel(issue.email_type);
        return issue.retry_for_id ? `${label} · ${t`Retry`}` : label;
    };

    return (
        <PageBody>
            <PageTitle
                subheading={t`Emails that failed to deliver, bounced, or were suppressed.`}
            >
                {t`Delivery Issues`}
            </PageTitle>

            {issuesQuery.isLoading && (
                <Card>
                    <TableSkeleton isVisible/>
                </Card>
            )}

            {!!issuesQuery.error && (
                <Alert color="red" radius="md">
                    {t`Failed to load delivery issues`}
                </Alert>
            )}

            {!issuesQuery.isLoading && !issuesQuery.error && (
                <Card>
                    <Group justify="flex-end" mb="md">
                        <Switch
                            label={t`Show resolved`}
                            checked={showResolved}
                            onChange={(e) => {
                                setShowResolved(e.currentTarget.checked);
                                setPage(1);
                            }}
                        />
                    </Group>

                    {issues && issues.length === 0 && (
                        <div className={classes.emptyState}>
                            <Text c="dimmed">
                                {showResolved
                                    ? t`No delivery issues found.`
                                    : t`No unresolved delivery issues.`
                                }
                            </Text>
                        </div>
                    )}

                    {issues && issues.length > 0 && (
                        <>
                            <Table striped highlightOnHover>
                                <Table.Thead>
                                    <Table.Tr>
                                        <Table.Th style={{width: 40}}>{t`Resolved`}</Table.Th>
                                        <Table.Th>{t`Type`}</Table.Th>
                                        <Table.Th>{t`Status`}</Table.Th>
                                        <Table.Th>{t`Subject`}</Table.Th>
                                        <Table.Th>{t`Recipient`}</Table.Th>
                                        <Table.Th>{t`Date`}</Table.Th>
                                        <Table.Th></Table.Th>
                                    </Table.Tr>
                                </Table.Thead>
                                <Table.Tbody>
                                    {issues.map((issue) => (
                                        <Table.Tr key={`${issue.source_type}-${issue.id}`} opacity={issue.resolved_at ? 0.6 : 1}>
                                            <Table.Td>
                                                <Tooltip label={issue.resolved_at ? t`Mark as unresolved` : t`Mark as resolved`}>
                                                    <ActionIcon
                                                        variant="subtle"
                                                        size="sm"
                                                        color={issue.resolved_at ? 'green' : 'gray'}
                                                        onClick={() => handleToggleResolved(issue)}
                                                        loading={resolveMutation.isPending}
                                                    >
                                                        {issue.resolved_at
                                                            ? <IconCheck size={16}/>
                                                            : <IconCircleDashed size={16}/>
                                                        }
                                                    </ActionIcon>
                                                </Tooltip>
                                            </Table.Td>
                                            <Table.Td>{sourceTypeLabel(issue)}</Table.Td>
                                            <Table.Td>
                                                <Badge size="sm" color={statusColor(issue.status)} variant="filled">
                                                    {issue.status}
                                                </Badge>
                                            </Table.Td>
                                            <Table.Td>{issue.subject}</Table.Td>
                                            <Table.Td>{issue.recipient}</Table.Td>
                                            <Table.Td>{relativeDate(issue.updated_at)}</Table.Td>
                                            <Table.Td>
                                                {!issue.resolved_at && !issue.retry_for_id && (
                                                    <Button
                                                        size="compact-xs"
                                                        variant="light"
                                                        onClick={() => setResolveModalMessage(issue)}
                                                    >
                                                        {t`Resolve`}
                                                    </Button>
                                                )}
                                            </Table.Td>
                                        </Table.Tr>
                                    ))}
                                </Table.Tbody>
                            </Table>

                            {pagination && Number(pagination.last_page) > 1 && (
                                <Pagination
                                    value={page}
                                    onChange={setPage}
                                    total={Number(pagination.last_page)}
                                    size="sm"
                                />
                            )}
                        </>
                    )}
                </Card>
            )}

            {resolveModalMessage && (
                <ResolveDeliveryIssueModal
                    onClose={() => setResolveModalMessage(null)}
                    eventId={eventId!}
                    message={resolveModalMessage}
                />
            )}
        </PageBody>
    );
};

export default DeliveryIssues;
