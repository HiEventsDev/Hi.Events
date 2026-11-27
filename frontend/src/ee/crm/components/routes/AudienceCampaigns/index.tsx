import {useState} from "react";
import {Link, useNavigate, useParams, useSearchParams} from "react-router";
import {plural, t} from "@lingui/macro";
import {Badge, SegmentedControl, Select} from "@mantine/core";
import {useDebouncedValue, useMediaQuery} from "@mantine/hooks";
import {IconPlus, IconSend, IconMessage} from "@tabler/icons-react";
import {CreateButton} from "../../../../../components/common/CreateButton";
import {Pagination} from "../../../../../components/common/Pagination";
import {SearchBar} from "../../../../../components/common/SearchBar";
import {TableSkeleton} from "../../../../../components/common/TableSkeleton";
import {TanStackTable, TanStackTableColumn} from "../../../../../components/common/TanStackTable";
import {IdentityCell} from "../../../../../components/common/TanStackTable/cells.tsx";
import {useAccess} from "../../../../../hooks/useAccess.ts";
import {useGetAccount} from "../../../../../queries/useGetAccount.ts";
import {useGetOrganizer} from "../../../../../queries/useGetOrganizer.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import {formatDateWithLocale, relativeDate} from "../../../../../utilites/dates.ts";
import {useCrmAccess} from "../../../hooks/useCrmAccess.ts";
import {useCrmReady} from "../../../hooks/useCrmReady.ts";
import {useGetCrmCampaigns} from "../../../queries/useGetCrmCampaigns.ts";
import {CrmCampaign, CrmCampaignFolder, CrmCampaignList, CrmCampaignListFilters} from "../../../types.ts";
import {formatCount, formatPercent} from "../../../utilites/format.ts";
import {canAttributeCampaignRevenue, isCampaignStopped, presetLabel} from "../../../utilites/labels.ts";
import {AudienceShell} from "../../AudienceShell";
import {LoadError} from "../../LoadError";
import {CampaignStatusBadge} from "../../CampaignStatusBadge";
import {NewCampaignModal} from "../../NewCampaignModal";
import shared from "../../Audience.module.scss";
import classes from "./Campaigns.module.scss";
import {Notice} from "../../Notice";
import {SendGateReason} from "../../SendGateReason";

type CampaignFilter = CrmCampaignFolder | 'all' | 'automated';

const FILTERS: CampaignFilter[] = ['all', 'drafts', 'scheduled', 'sent', 'automated'];

const listFilters = (filter: CampaignFilter, query: string): CrmCampaignListFilters => ({
    ...(filter === 'automated' ? {source: 'automation'} : {}),
    ...(filter === 'all' || filter === 'automated' ? {} : {folder: filter}),
    ...(query ? {query} : {}),
});

const filterCount = (filter: CampaignFilter, folders: CrmCampaignList['meta']['folders'] | undefined): number =>
    filter === 'all' || !folders ? 0 : folders[filter];

const parseFilter = (value: string | null): CampaignFilter =>
    FILTERS.includes(value as CampaignFilter) ? value as CampaignFilter : 'all';

const CAMPAIGN_CELL = {width: '65%', maxWidth: 0, minWidth: 240};
const AUDIENCE_CELL = {width: '35%', maxWidth: 0, minWidth: 160};

const tracksEngagement = (campaign: CrmCampaign): boolean =>
    campaign.channel !== 'SMS' && campaign.delivery_reports_available !== false;

const share = (campaign: CrmCampaign, value: number): string =>
    !tracksEngagement(campaign) || campaign.stats.sent === 0
        ? '–'
        : formatPercent(Math.round(value / campaign.stats.sent * 100));

const AudienceCampaigns = () => {
    const {organizerId} = useParams();
    const navigate = useNavigate();
    const {can} = useAccess();
    const {canChange, isLocked} = useCrmAccess();
    const isReady = useCrmReady(organizerId);
    const isNarrow = useMediaQuery('(max-width: 48em)');
    const isCompact = useMediaQuery('(max-width: 85em)');
    const {data: organizer} = useGetOrganizer(organizerId);
    const {data: account} = useGetAccount();
    const [searchParams, setSearchParams] = useSearchParams();
    const filter = parseFilter(searchParams.get('folder'));
    const page = Math.max(Number(searchParams.get('page')) || 1, 1);
    const query = searchParams.get('query') ?? '';
    const [debouncedQuery] = useDebouncedValue(query.trim(), 300);
    const campaignsQuery = useGetCrmCampaigns(organizerId, listFilters(filter, debouncedQuery), page, isReady);
    const basePath = `/manage/organizer/${organizerId}/audience/campaigns`;
    const campaigns = campaignsQuery.data?.data ?? [];
    const meta = campaignsQuery.data?.meta;
    const timezone = organizer?.timezone ?? 'UTC';
    const showEngagement = campaigns.some(tracksEngagement);
    const showRevenue = can('VIEW_FINANCIALS') && campaigns.some(canAttributeCampaignRevenue);
    const lacksDeliveryReports = campaigns.some(campaign => campaign.delivery_reports_available === false);
    const [isPicking, setIsPicking] = useState(false);
    const isSaas = !!account?.is_saas_mode_enabled;
    const sendGate = canChange ? meta?.send_gate : null;

    const filterLabels: Record<CampaignFilter, string> = {
        all: t`All`,
        drafts: t`Drafts`,
        scheduled: isSaas ? t`Scheduled or in review` : t`Scheduled`,
        sent: t`Sent`,
        automated: t`Automated`,
    };

    const emptyCopy: Record<CampaignFilter, string> = {
        all: t`No campaigns yet. Email a segment about your next event, or give your regulars first access to tickets.`,
        sent: t`Nothing sent yet.`,
        scheduled: isSaas ? t`Nothing is scheduled or waiting for review.` : t`Nothing is scheduled.`,
        drafts: t`No drafts. Start a campaign and it will be kept here until you send it.`,
        automated: t`Nothing has been sent by your automations yet.`,
    };

    const dateLabel = (campaign: CrmCampaign): string => {
        if (campaign.sent_at) {
            return formatDateWithLocale(campaign.sent_at, 'shortDateTime', timezone);
        }
        if (campaign.status === 'SCHEDULED' && campaign.scheduled_at) {
            return isLocked
                ? t`Paused`
                : t`Goes out ${formatDateWithLocale(campaign.scheduled_at, 'shortDateTime', timezone)}`;
        }
        if (campaign.status === 'PENDING_REVIEW') {
            return t`Submitted ${relativeDate(campaign.updated_at)}`;
        }
        return t`Edited ${relativeDate(campaign.updated_at)}`;
    };

    const allColumns: TanStackTableColumn<CrmCampaign>[] = [
        {
            id: 'campaign',
            header: t`Campaign`,
            meta: {cellStyle: CAMPAIGN_CELL},
            cell: ({row: {original: campaign}}) => isNarrow ? (
                <span className={classes.subject}>
                    <Link to={`${basePath}/${campaign.id}`} onClick={event => event.stopPropagation()}>
                        {campaign.channel === 'SMS' && <IconMessage size={14} className={classes.channelIcon} aria-label={t`Text message`}/>}
                        {campaign.subject || campaign.name}
                    </Link>
                    {campaign.subject && campaign.name !== campaign.subject && <small>{campaign.name}</small>}
                    <span className={classes.narrowMeta}>
                        <CampaignStatusBadge status={campaign.status} isStopped={isCampaignStopped(campaign)}/>
                        {dateLabel(campaign)}
                    </span>
                </span>
            ) : (
                <IdentityCell
                    primary={<span className={classes.subjectLink}><Link to={`${basePath}/${campaign.id}`} onClick={event => event.stopPropagation()}>
                            {campaign.channel === 'SMS' && <IconMessage size={14} className={classes.channelIcon} aria-label={t`Text message`}/>}
                            {campaign.subject || campaign.name}
                        </Link></span>}
                    secondary={campaign.subject && campaign.name !== campaign.subject ? campaign.name : undefined}
                />
            ),
        },
        {
            id: 'status',
            header: t`Status`,
            cell: ({row: {original: campaign}}) => (
                <span className={classes.statusCell}>
                    <CampaignStatusBadge status={campaign.status} isStopped={isCampaignStopped(campaign)}/>
                    {campaign.automation_id && filter !== 'automated' && (
                        <Badge variant="outline" color="gray" size="sm" radius="sm" tt="none">{t`Automation`}</Badge>
                    )}
                </span>
            ),
        },
        {
            id: 'audience',
            header: t`Audience`,
            meta: {cellStyle: AUDIENCE_CELL},
            cell: ({row: {original: campaign}}) => (
                <span className={classes.audience}>
                    {campaign.preset
                        ? presetLabel(campaign.preset)
                        : campaign.segment_name ?? (campaign.channel === 'SMS' ? t`Everyone who opted in to texts` : t`Everyone who opted in`)}
                </span>
            ),
        },
        {
            id: 'sent',
            header: t`Sent`,
            cell: ({row: {original: campaign}}) => (
                <span className={classes.number}>
                    {campaign.sent_at ? formatCount(campaign.stats.sent) : '–'}
                </span>
            ),
        },
        ...(showEngagement ? [{
            id: 'opened',
            header: t`Opened`,
            cell: ({row: {original: campaign}}) => (
                <span className={classes.number}>{share(campaign, campaign.stats.opened)}</span>
            ),
        }, {
            id: 'clicked',
            header: t`Clicked`,
            cell: ({row: {original: campaign}}) => (
                <span className={classes.number}>{share(campaign, campaign.stats.clicked)}</span>
            ),
        }] satisfies TanStackTableColumn<CrmCampaign>[] : []),
        ...(showRevenue ? [{
            id: 'revenue',
            header: t`Revenue`,
            cell: ({row: {original: campaign}}) => (
                <span className={classes.number}>
                    {(campaign.revenue ?? []).length === 0 || !canAttributeCampaignRevenue(campaign)
                        ? '–'
                        : (campaign.revenue ?? []).map(row => formatCurrency(row.attributed_revenue, row.currency)).join(' · ')}
                </span>
            ),
        } satisfies TanStackTableColumn<CrmCampaign>] : []),
        {
            id: 'date',
            header: t`Date`,
            cell: ({row: {original: campaign}}) => <span className={classes.muted}>{dateLabel(campaign)}</span>,
        },
    ];
    const columns = allColumns.filter(column => {
        if (isNarrow) {
            return column.id === 'campaign';
        }
        return !isCompact || column.id !== 'audience';
    });

    const setFilter = (next: string) => setSearchParams({
        ...(next === 'all' ? {} : {folder: next}),
        ...(query ? {query} : {}),
    });
    const setQuery = (next: string) => setSearchParams({
        ...(filter === 'all' ? {} : {folder: filter}),
        ...(next ? {query: next} : {}),
    });

    const filterOptions = FILTERS.map(value => {
        const count = filterCount(value, meta?.folders);
        return {value, label: count > 0 ? `${filterLabels[value]} · ${formatCount(count)}` : filterLabels[value]};
    });

    return (
        <AudienceShell
            actions={canChange && (
                <CreateButton
                    color="green"
                    rightSection={<IconPlus/>}
                    onClick={() => setIsPicking(true)}
                    data-testid="campaign-create-button"
                >
                    {t`New campaign`}
                </CreateButton>
            )}
        >
            {isLocked && meta && meta.folders.scheduled > 0 && (
                <Notice title={t`Scheduled campaigns are paused`}>
                    {plural(meta.scheduled_send_window_hours, {
                        one: 'Scheduled campaigns don\'t send while your licence is inactive. One that misses its time by more than # hour goes back to your drafts and we email you.',
                        other: 'Scheduled campaigns don\'t send while your licence is inactive. One that misses its time by more than # hours goes back to your drafts and we email you.',
                    })}
                </Notice>
            )}

            {sendGate?.blocked_reason && (
                <Notice title={t`You can't send campaigns yet`}>
                    <SendGateReason
                        organizerId={organizerId}
                        reason={sendGate.blocked_reason}
                        code={sendGate.blocked_reason_code}
                        params={sendGate.blocked_reason_params}
                    />
                </Notice>
            )}

            <div className={classes.toolbar}>
                <SearchBar
                    className={classes.search}
                    aria-label={t`Search campaigns`}
                    placeholder={t`Search by subject or name`}
                    value={query}
                    onChange={event => setQuery(event.currentTarget.value)}
                    onClear={() => setQuery('')}
                    data-testid="campaign-search"
                />
                {isNarrow ? (
                    <Select
                        className={classes.filterSelect}
                        aria-label={t`Filter campaigns`}
                        data={filterOptions}
                        value={filter}
                        onChange={value => setFilter(value ?? 'all')}
                        allowDeselect={false}
                    />
                ) : (
                    <SegmentedControl
                        size="xs"
                        value={filter}
                        onChange={setFilter}
                        aria-label={t`Filter campaigns`}
                        data={filterOptions}
                    />
                )}
                {meta && <span className={classes.total}>{plural(meta.total, {one: '# campaign', other: '# campaigns'})}</span>}
            </div>

            <TableSkeleton isVisible={campaignsQuery.isLoading} numRows={6}/>

            {campaignsQuery.isError && (
                <div className={shared.panel}>
                    <LoadError message={t`We couldn't load your campaigns.`} onRetry={() => campaignsQuery.refetch()}/>
                </div>
            )}

            {campaignsQuery.data && campaigns.length === 0 && (
                <div className={shared.panel}>
                    <div className={shared.empty}>
                        {debouncedQuery || filter !== 'all'
                            ? <IconSend size={22} stroke={1.5}/>
                            : <img className={shared.emptyImage} src="/blank-slate/audience-campaigns.svg" alt=""/>}
                        <p>{debouncedQuery ? t`No campaigns match your search.` : emptyCopy[filter]}</p>
                    </div>
                </div>
            )}

            {campaigns.length > 0 && (
                <div className={classes.table} data-narrow={isNarrow || undefined}>
                    <TanStackTable
                        data={campaigns}
                        columns={columns}
                        isRefreshing={campaignsQuery.isPlaceholderData}
                        onRowClick={campaign => navigate(`${basePath}/${campaign.id}`)}
                    />
                </div>
            )}

            {campaigns.length > 0 && lacksDeliveryReports && (
                <p className={classes.footnote} data-testid="campaigns-no-delivery-reports">
                    {t`Opens and clicks aren't shown because this installation's mail service doesn't send delivery reports.`}
                </p>
            )}

            {meta && meta.last_page > 1 && (
                <Pagination
                    value={page}
                    total={meta.last_page}
                    onChange={value => setSearchParams({
                        ...(filter === 'all' ? {} : {folder: filter}),
                        ...(query ? {query} : {}),
                        ...(value > 1 ? {page: String(value)} : {}),
                    })}
                />
            )}
            {isPicking && <NewCampaignModal organizerId={organizerId} onClose={() => setIsPicking(false)}/>}
        </AudienceShell>
    );
};

export default AudienceCampaigns;
