import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {PageBody} from "../../../common/PageBody";
import {ActionIcon, Alert, Badge, Button, Group, Switch, Table, Text, Tooltip} from "@mantine/core";
import {Card} from "../../../common/Card";
import {useParams} from "react-router";
import {useGetTransactionMessageFailures} from "../../../../queries/useGetTransactionMessageFailures.ts";
import {relativeDate} from "../../../../utilites/dates.ts";
import {Pagination} from "../../../common/Pagination";
import {useState} from "react";
import {TableSkeleton} from "../../../common/TableSkeleton";
import {IconCheck, IconCircleDashed} from "@tabler/icons-react";
import {useResolveTransactionMessage} from "../../../../mutations/useResolveTransactionMessage.ts";
import {ResolveDeliveryIssueModal} from "../../../modals/ResolveDeliveryIssueModal";
import {OutgoingTransactionMessage} from "../../../../types.ts";
import {showError} from "../../../../utilites/notifications.tsx";
import classes from "./DeliveryIssues.module.scss";

const statusColor = (status: string) => {
    switch (status?.toUpperCase()) {
        case 'DELIVERED':
            return 'green';
        case 'BOUNCED':
            return 'red';
        case 'FAILED':
            return 'orange';
        case 'SUPPRESSED':
            return 'gray';
        default:
            return 'blue';
    }
};

const emailTypeLabel = (emailType: string) => {
    switch (emailType) {
        case 'order_summary':
            return t`Order Confirmation`;
        case 'order_failed':
            return t`Order Failed`;
        case 'attendee_ticket':
            return t`Attendee Ticket`;
        case 'waitlist_offer':
            return t`Waitlist Offer`;
        case 'waitlist_confirmation':
            return t`Waitlist Confirmation`;
        case 'waitlist_offer_expired':
            return t`Waitlist Offer Expired`;
        default:
            return emailType;
    }
};

const DeliveryIssues = () => {
    const {eventId} = useParams();
    const [page, setPage] = useState(1);
    const [showResolved, setShowResolved] = useState(true);
    const [resolveModalMessage, setResolveModalMessage] = useState<OutgoingTransactionMessage | null>(null);
    const failuresQuery = useGetTransactionMessageFailures(eventId, {pageNumber: page, perPage: 20}, showResolved);
    const failures = failuresQuery.data?.data;
    const pagination = failuresQuery.data?.meta;
    const resolveMutation = useResolveTransactionMessage();

    const handleToggleResolved = (message: OutgoingTransactionMessage) => {
        resolveMutation.mutate({
            eventId: eventId!,
            messageId: message.id!,
        }, {
            onError: () => showError(t`Failed to update resolved status.`),
        });
    };

    return (
        <PageBody>
            <PageTitle
                subheading={t`Transactional emails that failed to deliver, bounced, or were suppressed.`}
            >
                {t`Delivery Issues`}
            </PageTitle>

            {failuresQuery.isLoading && (
                <Card>
                    <TableSkeleton isVisible/>
                </Card>
            )}

            {!!failuresQuery.error && (
                <Alert color="red" radius="md">
                    {t`Failed to load delivery issues`}
                </Alert>
            )}

            {!failuresQuery.isLoading && !failuresQuery.error && (
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

                    {failures && failures.length === 0 && (
                        <div className={classes.emptyState}>
                            <Text c="dimmed">
                                {showResolved
                                    ? t`No delivery issues found.`
                                    : t`No unresolved delivery issues. All transactional emails have been delivered successfully.`
                                }
                            </Text>
                        </div>
                    )}

                    {failures && failures.length > 0 && (
                        <>
                            <Table striped highlightOnHover>
                                <Table.Thead>
                                    <Table.Tr>
                                        <Table.Th style={{width: 40}}>{t`Status`}</Table.Th>
                                        <Table.Th>{t`Recipient`}</Table.Th>
                                        <Table.Th>{t`Email Type`}</Table.Th>
                                        <Table.Th>{t`Subject`}</Table.Th>
                                        <Table.Th>{t`Status`}</Table.Th>
                                        <Table.Th>{t`Date`}</Table.Th>
                                        <Table.Th></Table.Th>
                                    </Table.Tr>
                                </Table.Thead>
                                <Table.Tbody>
                                    {failures.map((failure) => (
                                        <Table.Tr key={String(failure.id)} opacity={failure.resolved_at ? 0.6 : 1}>
                                            <Table.Td>
                                                <Tooltip label={failure.resolved_at ? t`Mark as unresolved` : t`Mark as resolved`}>
                                                    <ActionIcon
                                                        variant="subtle"
                                                        size="sm"
                                                        color={failure.resolved_at ? 'green' : 'gray'}
                                                        onClick={() => handleToggleResolved(failure)}
                                                        loading={resolveMutation.isPending}
                                                    >
                                                        {failure.resolved_at
                                                            ? <IconCheck size={16}/>
                                                            : <IconCircleDashed size={16}/>
                                                        }
                                                    </ActionIcon>
                                                </Tooltip>
                                            </Table.Td>
                                            <Table.Td>{failure.recipient}</Table.Td>
                                            <Table.Td>{emailTypeLabel(failure.email_type)}</Table.Td>
                                            <Table.Td>{failure.subject}</Table.Td>
                                            <Table.Td>
                                                <Badge size="sm" color={statusColor(failure.status)} variant="filled">
                                                    {failure.status}
                                                </Badge>
                                            </Table.Td>
                                            <Table.Td>{relativeDate(failure.created_at)}</Table.Td>
                                            <Table.Td>
                                                {!failure.resolved_at && (
                                                    <Button
                                                        size="compact-xs"
                                                        variant="light"
                                                        onClick={() => setResolveModalMessage(failure)}
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
