import {useState} from "react";
import {useParams, useSearchParams} from "react-router";
import {Alert, Badge, Button, Checkbox, Group, Loader, Select, Table, Text, TextInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {Card} from "../../../common/Card";
import {Pagination} from "../../../common/Pagination";
import {WriteAccess} from "../../../common/WriteAccess";
import {useAccess} from "../../../../hooks/useAccess";
import {useGetOrganizerSettings} from "../../../../queries/useGetOrganizerSettings";
import {useGetOrganizerSubscribers} from "../../../../queries/useGetOrganizerSubscribers";
import {useExportOrganizerSubscribers, useUnsubscribeOrganizerSubscribers} from "../../../../mutations/useOrganizerSubscription";
import {confirmationDialog} from "../../../../utilites/confirmationDialog";
import {showError, showSuccess} from "../../../../utilites/notifications";
import {formatDateWithLocale} from "../../../../utilites/dates";
import {useGetOrganizer} from "../../../../queries/useGetOrganizer";
import {downloadBinary} from "../../../../utilites/download";
import {OrganizerSubscriber, SubscriberFilters} from "../../../../api/organizer-subscription.client";
import classes from "./Subscriptions.module.scss";

export default function Subscribers() {
    const {organizerId} = useParams();
    const [params, setParams] = useSearchParams();
    const {canManageOrganizer, isLoaded, can} = useAccess();
    const allowed = isLoaded && canManageOrganizer(organizerId);
    const filters: SubscriberFilters = {query: params.get('query') || '', status: params.get('status') || '', page: Math.max(1, Number(params.get('page')) || 1)};
    const subscribers = useGetOrganizerSubscribers(organizerId, filters, allowed);
    const settings = useGetOrganizerSettings(organizerId);
    const organizer = useGetOrganizer(organizerId);
    const unsubscribe = useUnsubscribeOrganizerSubscribers(organizerId);
    const exportCsv = useExportOrganizerSubscribers(organizerId);
    const [selection, setSelection] = useState<number[]>([]);
    const [selectionKey, setSelectionKey] = useState('');
    const key = `${organizerId}:${filters.query}:${filters.status}:${filters.page}`;
    const selected = selectionKey === key ? selection : [];
    const setSelected = (ids: number[]) => {setSelectionKey(key); setSelection(ids);};
    const changeFilters = (updates: Partial<SubscriberFilters>) => {
        const next = {...filters, page: 1, ...updates};
        setParams({query: next.query, status: next.status, page: String(next.page)});
        setSelected([]);
    };
    const rows = subscribers.data?.data || [];
    const eligible = rows.filter(row => row.status !== 'unsubscribed').map(row => row.id);
    const unsubscribeIds = (ids: number[]) => {
        const count = ids.length;
        confirmationDialog(t`Unsubscribe ${count} selected subscribers?`, () => unsubscribe.mutate(ids, {
            onSuccess: () => {setSelected([]); showSuccess(t`Subscribers unsubscribed`);},
            onError: () => showError(t`Unable to unsubscribe subscribers. Please try again.`),
        }));
    };
    const labels: Record<OrganizerSubscriber['status'], string> = {confirmed: t`Confirmed`, pending: t`Pending confirmation`, unsubscribed: t`Unsubscribed`};
    const displayDate = (value: string | null) => value ? formatDateWithLocale(value, 'shortDateTime', organizer.data?.timezone || 'UTC') : '—';

    if (!isLoaded) return <Loader/>;
    if (!allowed) return <PageBody><Alert color="red">{t`You don't have access to this page`}</Alert></PageBody>;

    return <PageBody>
        <PageTitle subheading={t`Manage people who subscribed to your new events.`}>{t`Subscribers`}</PageTitle>
        {settings.data && !settings.data.new_event_notifications_enabled && <Alert title={t`Notifications paused`}>
            {t`Subscriptions are retained. Enable new-event notifications in organizer settings to accept signups and announce future events.`}
        </Alert>}
        <div className={classes.counts}>
            {(['confirmed', 'pending', 'unsubscribed'] as const).map(status => <Badge key={status} size="lg" variant="light">{labels[status]}: {subscribers.data?.meta.counts[status] ?? 0}</Badge>)}
        </div>
        <div className={classes.toolbar}>
            <TextInput aria-label={t`Search subscribers by email`} placeholder={t`Search by email`} value={filters.query} onChange={event => changeFilters({query: event.currentTarget.value})}/>
            <Select aria-label={t`Subscription status`} value={filters.status} onChange={value => changeFilters({status: value || ''})}
                data={[{value: '', label: t`All statuses`}, ...Object.entries(labels).map(([value, label]) => ({value, label}))]}/>
            <Button variant="default" loading={exportCsv.isPending} disabled={!subscribers.data?.meta.total} onClick={() => exportCsv.mutate(filters, {
                onSuccess: data => downloadBinary(data, 'subscribers.csv'),
                onError: () => showError(t`Unable to export subscribers. Please try again.`),
            })}>{t`Export CSV`}</Button>
            <WriteAccess><Button color="red" variant="light" disabled={!selected.length || unsubscribe.isPending} onClick={() => unsubscribeIds(selected)}>{t`Unsubscribe selected`} ({selected.length})</Button></WriteAccess>
        </div>
        {subscribers.isError ? <Alert color="red" role="alert">{t`Unable to load subscribers.`}</Alert> : subscribers.isPending ? <Loader/> : <>
            <Card><div className={classes.table}><Table miw={850}>
                <Table.Thead><Table.Tr>
                    {can('EDIT') && <Table.Th><Checkbox aria-label={t`Select all subscribers on this page`} checked={eligible.length > 0 && eligible.every(id => selected.includes(id))}
                        indeterminate={selected.length > 0 && !eligible.every(id => selected.includes(id))}
                        disabled={!eligible.length || unsubscribe.isPending} onChange={event => setSelected(event.currentTarget.checked ? eligible : [])}/></Table.Th>}
                    <Table.Th>{t`Email`}</Table.Th><Table.Th>{t`Status`}</Table.Th><Table.Th>{t`Signup source`}</Table.Th><Table.Th>{t`Subscription date`}</Table.Th><Table.Th>{t`Confirmation date`}</Table.Th><Table.Th>{t`Unsubscribe date`}</Table.Th>
                    {can('EDIT') && <Table.Th>{t`Actions`}</Table.Th>}
                </Table.Tr></Table.Thead>
                <Table.Tbody>{rows.map(row => <Table.Tr key={row.id}>
                    {can('EDIT') && <Table.Td><Checkbox aria-label={t`Select ${row.email}`} disabled={row.status === 'unsubscribed' || unsubscribe.isPending} checked={selected.includes(row.id)}
                        onChange={event => setSelected(event.currentTarget.checked ? [...selected, row.id] : selected.filter(id => id !== row.id))}/></Table.Td>}
                    <Table.Td>{row.email}</Table.Td><Table.Td>{labels[row.status]}</Table.Td><Table.Td>{row.source === 'checkout' ? t`Checkout` : t`Public signup`}</Table.Td>
                    <Table.Td>{displayDate(row.subscribed_at)}</Table.Td><Table.Td>{displayDate(row.confirmed_at)}</Table.Td><Table.Td>{displayDate(row.unsubscribed_at)}</Table.Td>
                    {can('EDIT') && <Table.Td><Button size="xs" variant="subtle" color="red" disabled={row.status === 'unsubscribed' || unsubscribe.isPending} onClick={() => unsubscribeIds([row.id])}>{t`Unsubscribe`}</Button></Table.Td>}
                </Table.Tr>)}</Table.Tbody>
            </Table></div>{!rows.length && <Text ta="center" py="xl">{t`No subscribers found.`}</Text>}</Card>
            <Group justify="flex-end"><Pagination value={filters.page} total={subscribers.data?.meta.last_page || 1} onChange={page => changeFilters({page})}/></Group>
        </>}
    </PageBody>;
}
