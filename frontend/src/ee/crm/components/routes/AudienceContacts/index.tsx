import {useEffect, useState} from "react";
import {Link, useParams} from "react-router";
import {plural, t} from "@lingui/macro";
import {Anchor, Button, Select} from "@mantine/core";
import {useElementSize, useMediaQuery} from "@mantine/hooks";
import {IconAddressBook, IconAlertTriangle, IconFilter, IconMail, IconMailOff, IconMessage} from "@tabler/icons-react";
import {FeatureFlag} from "../../../../../constants/featureFlags.ts";
import {Pagination} from "../../../../../components/common/Pagination";
import {SearchBar} from "../../../../../components/common/SearchBar";
import {TableSkeleton} from "../../../../../components/common/TableSkeleton";
import {useGetOrganizer} from "../../../../../queries/useGetOrganizer.ts";
import {apiErrorStatus, firstApiError} from "../../../../../utilites/apiErrors.ts";
import {showError} from "../../../../../utilites/notifications.tsx";
import {useLicensedFeature} from "../../../../licensing/hooks/useLicensedFeature.ts";
import {useCrmAccess} from "../../../hooks/useCrmAccess.ts";
import {useCrmReady} from "../../../hooks/useCrmReady.ts";
import {useFollowCrmImports} from "../../../hooks/useFollowCrmImports.ts";
import {useTextMessagesVisible} from "../../../hooks/useTextMessagesVisible.ts";
import {useExportCrmContacts} from "../../../mutations/useExportCrmContacts.ts";
import {useExportSelectedCrmContacts} from "../../../mutations/useCrmBulkMutations.ts";
import {useGetCrmContacts} from "../../../queries/useGetCrmContacts.ts";
import {useGetCrmSuppressedContacts, useGetCrmSuppressionSummary} from "../../../queries/useCrmSuppressionQueries.ts";
import {useGetCrmSegmentCount} from "../../../queries/useGetCrmSegmentCount.ts";
import {useGetCrmSegments} from "../../../queries/useGetCrmSegments.ts";
import {useGetCrmSmsConnection} from "../../../queries/useGetCrmSmsConnection.ts";
import {useGetCrmSyncStatus} from "../../../queries/useGetCrmSyncStatus.ts";
import {CrmContact} from "../../../types.ts";
import {segmentParam} from "../../../utilites/format.ts";
import {isEveryoneOnOneChannel, presetDescription, presetLabel} from "../../../utilites/labels.ts";
import {deletedSegmentOptions} from "../../../utilites/segmentOptions.ts";
import {AudienceShell} from "../../AudienceShell";
import {BulkActionBar} from "../../BulkActionBar";
import {BulkConfirmModal} from "../../BulkActionBar/BulkConfirmModal.tsx";
import {ContactDrawer} from "../../ContactDrawer";
import {LoadError} from "../../LoadError";
import {ContactFilters, rejectedRuleErrors} from "./ContactFilters.tsx";
import {ContactsHeaderActions} from "./ContactsHeaderActions.tsx";
import {ContactsModal, ContactsModals} from "./ContactsModals.tsx";
import {ContactsTable} from "./ContactsTable.tsx";
import {SuppressedTable} from "./SuppressedTable.tsx";
import {SuppressionReasonFilter} from "./SuppressionReasonFilter.tsx";
import {useBulkContactActions} from "./useBulkContactActions.tsx";
import {useContactSelection} from "./useContactSelection.ts";
import {SUPPRESSED_SCOPE, useContactsUrlState} from "./useContactsUrlState.ts";
import shared from "../../Audience.module.scss";
import classes from "./Contacts.module.scss";
import {Notice} from "../../Notice";

const PER_PAGE = 25;
const COMPACT_LIST_WIDTH = 860;

const AudienceContacts = () => {
    const {organizerId} = useParams();
    const isReady = useCrmReady(organizerId);
    const {data: organizer} = useGetOrganizer(organizerId);
    const {data: syncStatus} = useGetCrmSyncStatus(organizerId, isReady);
    const segmentsQuery = useGetCrmSegments(organizerId, isReady);
    const {canChange, canRemove} = useCrmAccess();
    useFollowCrmImports(organizerId, isReady);
    const showTextMessages = useTextMessagesVisible();
    const textMessages = useLicensedFeature(FeatureFlag.CRM_SMS);
    const smsConnectionQuery = useGetCrmSmsConnection(organizerId, isReady && textMessages.isEnabled);
    const listCard = useElementSize();
    const isSmallViewport = useMediaQuery('(max-width: 62em)');
    const isCompact = listCard.width > 0 ? listCard.width < COMPACT_LIST_WIDTH : !!isSmallViewport;
    const exportContacts = useExportCrmContacts();
    const exportSelected = useExportSelectedCrmContacts();
    const [modal, setModal] = useState<ContactsModal | null>(null);
    const [columnToggleSlot, setColumnToggleSlot] = useState<HTMLDivElement | null>(null);
    const url = useContactsUrlState();
    const {isSuppressed, query, page, sortBy, sortDirection, reason, selection, filter, segmentReference, draft, setDraft} = url;

    const timezone = organizer?.timezone ?? 'UTC';
    const segments = segmentsQuery.data;
    const presets = (segments?.presets ?? []).filter(item => showTextMessages || item.key !== 'sms_subscribers');
    const preset = selection.kind === 'preset' ? segments?.presets.find(item => item.key === selection.preset) : undefined;
    const saved = selection.kind === 'saved' ? segments?.data.find(item => item.id === selection.segmentId) : undefined;
    const isSegmentDeleted = selection.kind === 'saved' && !!segments && !saved;
    const isSegmentRestricted = !filter && !!saved?.has_restricted_rules;
    const isSegmentUnavailable = isSegmentDeleted || isSegmentRestricted;

    const pagination = {pageNumber: page, perPage: PER_PAGE, query, sortBy, sortDirection};
    const contactsQuery = useGetCrmContacts(organizerId, pagination, segmentReference, isReady && !isSuppressed && !isSegmentUnavailable);
    const suppressedQuery = useGetCrmSuppressedContacts(organizerId, pagination, segmentReference ?? {}, isReady && isSuppressed);
    const listQuery = isSuppressed ? suppressedQuery : contactsQuery;
    const summaryQuery = useGetCrmSuppressionSummary(organizerId, isReady && isSuppressed);
    const segmentCountQuery = useGetCrmSegmentCount(organizerId, segmentReference ?? {}, isReady && !isSuppressed && !!segmentReference && !isSegmentUnavailable);
    const isFilterRejected = !!filter && contactsQuery.isError && apiErrorStatus(contactsQuery.error) === 422;

    const list = isSegmentUnavailable ? undefined : listQuery.data;
    const contacts = list?.data ?? [];
    const meta = list?.meta;
    const summary = summaryQuery.data;
    const lastPage = meta?.last_page;

    useEffect(() => {
        if (lastPage !== undefined && page > Math.max(lastPage, 1)) {
            url.setPage(Math.max(lastPage, 1));
        }
    }, [lastPage, page]);

    const subscribedCount = (() => {
        if (isSuppressed) {
            return undefined;
        }
        if (segmentReference) {
            return !filter && segmentCountQuery.isPlaceholderData ? undefined : segmentCountQuery.data?.mailable_count;
        }
        return syncStatus?.mailable_count;
    })();

    const scopeValue = isSuppressed ? SUPPRESSED_SCOPE : segmentParam(selection) ?? 'all';

    const scopeOptions = [
        {value: 'all', label: t`All contacts`},
        {
            group: t`Ready-made segments`,
            items: presets.map(item => ({value: `preset:${item.key}`, label: presetLabel(item.key)})),
        },
        ...((segments?.data.length ?? 0) > 0 || isSegmentDeleted ? [{
            group: t`Your segments`,
            items: [
                ...(segments?.data ?? []).map(item => ({value: `saved:${item.id}`, label: item.name})),
                ...deletedSegmentOptions(scopeValue, segments).flatMap(group => group.items),
            ],
        }] : []),
        {group: t`Can't be emailed`, items: [{value: SUPPRESSED_SCOPE, label: t`Suppressed`}]},
    ];

    const selectionDescription = filter || isSuppressed
        ? null
        : selection.kind === 'preset' ? presetDescription(selection.preset) : saved?.description;

    const isTextSubscribersScope = selection.kind === 'preset' && selection.preset === 'sms_subscribers';
    const canSendTexts = textMessages.isEnabled && !!smsConnectionQuery.data;

    const showsSubscribedCount = !query && subscribedCount !== undefined
        && !(selection.kind === 'preset' && !filter && isEveryoneOnOneChannel(selection.preset));

    const isFiltered = query !== '' || selection.kind !== 'all' || !!filter || reason !== null;

    const total = meta?.total ?? 0;
    const pageIds = contacts.map(contact => String(contact.id));
    const selected = useContactSelection({listKey: url.listKey, query, segmentReference, total, pageIds});

    const bulk = useBulkContactActions({
        organizerId,
        selection: selected.bulkSelection,
        count: selected.count,
        onFinished: selected.clear,
    });

    const exportCurrent = () => exportContacts.mutate({
        organizerId,
        pagination: {pageNumber: 1, query, sortBy, sortDirection},
        segment: segmentReference,
    }, {
        onError: error => showError(firstApiError(error, t`We couldn't export these contacts. Please try again.`)),
    });

    const exportSelection = () => {
        if (selected.allMatching) {
            exportCurrent();
            return;
        }
        exportSelected.mutate({organizerId, contactIds: [...selected.selectedIds].map(Number)}, {
            onError: error => showError(firstApiError(error, t`We couldn't export these contacts. Please try again.`)),
        });
    };

    const visibleSegmentRules = preset?.definition ?? (saved?.has_restricted_rules ? undefined : saved?.definition);
    const openFilters = () => setDraft(filter ?? visibleSegmentRules ?? {match: 'all', rules: []});

    const emptyMessage = () => {
        if (isSuppressed) {
            return isFiltered
                ? t`Nobody matches this search or reason.`
                : t`Nobody is suppressed. People who unsubscribe, bounce or mark an email as spam appear here, and campaigns skip them.`;
        }

        if (isFiltered) {
            return t`Nobody matches this search or filter.`;
        }

        return canChange
            ? t`No contacts yet. They appear here as soon as someone books, or you can add one yourself.`
            : t`No contacts yet. They appear here as soon as someone books.`;
    };

    return (
        <AudienceShell
            actions={(
                <ContactsHeaderActions
                    organizerId={organizerId}
                    canChange={canChange}
                    canRemove={canRemove}
                    isExporting={exportContacts.isPending}
                    onExport={exportCurrent}
                    onOpenModal={setModal}
                />
            )}
        >
            <section className={classes.listCard} ref={listCard.ref}>
                <div className={classes.controlBar}>
                    <Select
                        className={classes.segmentSelect}
                        data-active={scopeValue !== 'all' || undefined}
                        aria-label={t`Contacts to show`}
                        data={scopeOptions}
                        value={filter ? null : scopeValue}
                        placeholder={t`Custom filter`}
                        onChange={url.selectScope}
                        searchable
                        allowDeselect={false}
                        maxDropdownHeight={360}
                        nothingFoundMessage={t`No segments match`}
                    />
                    <div className={classes.tools}>
                        <div className={classes.search}>
                            <SearchBar
                                aria-label={t`Search contacts`}
                                placeholder={t`Search name or email`}
                                value={query}
                                onChange={event => url.setQuery(event.currentTarget.value || null)}
                                onClear={() => url.setQuery(null)}
                                data-testid="audience-contact-search"
                            />
                        </div>
                        {!isSuppressed && (
                            <Button
                                variant={filter ? 'outline' : 'light'}
                                color={filter ? 'primary' : 'gray'}
                                leftSection={<IconFilter size={16}/>}
                                onClick={() => draft ? setDraft(null) : openFilters()}
                                aria-expanded={!!draft}
                            >
                                {filter ? plural(filter.rules.length, {one: 'Filter · # rule', other: 'Filter · # rules'}) : t`Filter`}
                            </Button>
                        )}
                    </div>
                </div>

                {draft && !isSuppressed && (
                    <ContactFilters
                        organizerId={organizerId}
                        value={draft}
                        errors={isFilterRejected ? rejectedRuleErrors(contactsQuery.error, draft) : undefined}
                        canSave={canChange && !!filter && !isFilterRejected}
                        onChange={url.changeFilter}
                        onSave={() => setModal('save')}
                        onClear={url.clearFilter}
                        onClose={() => setDraft(null)}
                    />
                )}

                {isSuppressed && (
                    <SuppressionReasonFilter
                        reason={reason}
                        summary={summary}
                        isCompact={isCompact}
                        onChange={url.setReason}
                        onCheckAddress={() => setModal('check')}
                    />
                )}

                <div className={classes.resultSummary} data-empty={((listQuery.isError || isSegmentUnavailable) && !meta) || undefined}>
                    {meta && (
                        <span data-testid="audience-contact-total">
                            <strong>{plural(meta.total, {one: '# contact', other: '# contacts'})}</strong>
                        </span>
                    )}
                    {isSuppressed && <span className={classes.note}>{t`Campaigns skip everyone on this list.`}</span>}
                    {showsSubscribedCount && (
                        <span className={classes.reachable}>{plural(subscribedCount, {one: '# subscribed', other: '# subscribed'})}</span>
                    )}
                    {selectionDescription && <span className={classes.description}>{selectionDescription}</span>}
                    {canChange && !isSuppressed && !isTextSubscribersScope && !query && !filter && (subscribedCount ?? 0) > 0 && (
                        <Link to={`/manage/organizer/${organizerId}/audience/campaigns/new${selection.kind === 'all' ? '' : `?segment=${segmentParam(selection)}`}`}>
                            <IconMail size={14}/>{selection.kind === 'all' ? t`Email your subscribers` : t`Email this segment`}
                        </Link>
                    )}
                    {canChange && canSendTexts && !isSuppressed && selection.kind !== 'all' && !query && !filter && (segmentCountQuery.data?.textable_count ?? 0) > 0 && (
                        <Link to={`/manage/organizer/${organizerId}/audience/campaigns/new?channel=SMS${isTextSubscribersScope ? '' : `&segment=${segmentParam(selection)}`}`}>
                            <IconMessage size={14}/>{t`Text this segment`}
                        </Link>
                    )}
                    <div className={classes.columnToggle} ref={setColumnToggleSlot}/>
                </div>

                <TableSkeleton isVisible={listQuery.isLoading} numRows={8}/>

                {isSegmentDeleted && (
                    <div className={classes.listMessage}>
                        <Notice tone="danger">{t`This segment has been deleted. Pick another one to see its contacts.`}</Notice>
                    </div>
                )}

                {isSegmentRestricted && (
                    <div className={classes.listMessage}>
                        <Notice tone="info">{t`This segment filters by spend, which your role can't see. Pick another one to see its contacts.`}</Notice>
                    </div>
                )}

                {isFilterRejected && (
                    <div className={shared.empty} role="alert">
                        <IconAlertTriangle size={22} stroke={1.5}/>
                        <p>{t`This filter can't be applied.`} {firstApiError(contactsQuery.error, '')}</p>
                        <Button variant="default" size="xs" onClick={url.clearFilter}>{t`Clear filter`}</Button>
                    </div>
                )}

                {listQuery.isError && !isSegmentUnavailable && !isFilterRejected && (
                    <LoadError message={t`We couldn't load these contacts.`} onRetry={() => listQuery.refetch()}/>
                )}

                {meta?.total === 0 && (
                    <div className={shared.empty}>
                        {isSuppressed ? <IconMailOff size={22} stroke={1.5}/> : <IconAddressBook size={22} stroke={1.5}/>}
                        <p>{emptyMessage()}</p>
                        {isFiltered && (
                            <Button
                                variant="default"
                                size="xs"
                                component={Link}
                                to={`/manage/organizer/${organizerId}/audience/contacts`}
                                onClick={() => setDraft(null)}
                            >
                                {t`Show all contacts`}
                            </Button>
                        )}
                    </div>
                )}

                {contacts.length > 0 && selected.isPageSelected && total > contacts.length && (
                    <div className={classes.selectAllBanner}>
                        {selected.allMatching ? (
                            <>
                                {plural(total, {one: 'The # matching contact is selected.', other: 'All # contacts are selected.'})}
                                <Anchor component="button" type="button" size="sm" onClick={selected.clear}>{t`Clear selection`}</Anchor>
                            </>
                        ) : (
                            <>
                                {plural(contacts.length, {one: 'The # contact on this page is selected.', other: 'All # contacts on this page are selected.'})}
                                <Anchor component="button" type="button" size="sm" onClick={selected.selectAllMatching} data-testid="audience-select-all-matching">
                                    {plural(total, {one: 'Select the # matching contact', other: 'Select all # matching contacts'})}
                                </Anchor>
                            </>
                        )}
                    </div>
                )}

                {contacts.length > 0 && (
                    <div className={classes.table} data-refreshing={(isCompact && listQuery.isPlaceholderData) || undefined}>
                        {isSuppressed ? (
                            <SuppressedTable
                                contacts={contacts}
                                isCompact={isCompact}
                                isRefreshing={listQuery.isPlaceholderData}
                                timezone={timezone}
                                sortBy={sortBy}
                                sortDirection={sortDirection}
                                onSort={url.setSort}
                                onOpen={(contact: CrmContact) => url.openContact(contact.id)}
                                selectedIds={selected.visibleSelectedIds}
                                onSelectionChange={selected.change}
                            />
                        ) : (
                            <ContactsTable
                                contacts={contacts}
                                isCompact={isCompact}
                                showTextMessages={showTextMessages}
                                sortBy={sortBy}
                                sortDirection={sortDirection}
                                onSort={url.setSort}
                                onOpen={(contact: CrmContact) => url.openContact(contact.id)}
                                selectedIds={selected.visibleSelectedIds}
                                onSelectionChange={selected.change}
                                columnToggleSlot={columnToggleSlot}
                                isRefreshing={listQuery.isPlaceholderData}
                            />
                        )}
                    </div>
                )}
            </section>

            {meta && meta.last_page > 1 && (
                <Pagination
                    value={page}
                    total={meta.last_page}
                    onChange={value => {
                        url.setPage(value);
                        document.getElementById('app-manage-main')?.scrollTo({top: 0, behavior: 'smooth'});
                    }}
                />
            )}

            {(selected.bulkSelection || bulk.progress) && (
                <BulkActionBar
                    organizerId={organizerId}
                    count={selected.count}
                    progress={bulk.progress}
                    isBusy={bulk.isBusy || exportSelected.isPending || exportContacts.isPending}
                    tagDisabledReason={canChange ? null : canRemove ? t`Your licence doesn't include Audience, so tags can't be changed` : t`Your role can't edit contacts`}
                    removeDisabledReason={canRemove ? null : t`Your role can't edit contacts`}
                    onAddTag={isSuppressed ? undefined : bulk.addTag}
                    onRemoveTag={isSuppressed ? undefined : bulk.removeTag}
                    onExport={exportSelection}
                    onUnsubscribe={isSuppressed ? undefined : bulk.askUnsubscribe}
                    onDelete={bulk.askDelete}
                    onClear={selected.clear}
                />
            )}

            {bulk.confirmation && (
                <BulkConfirmModal
                    key={`${bulk.confirmation.action}-${bulk.confirmation.count}`}
                    action={bulk.confirmation.action}
                    count={bulk.confirmation.count}
                    changedCount={bulk.confirmation.changedCount}
                    isPending={bulk.isConfirming}
                    onConfirm={bulk.confirm}
                    onClose={bulk.cancelConfirmation}
                />
            )}

            {url.contactId && (
                <ContactDrawer
                    organizerId={organizerId}
                    contactId={url.contactId}
                    timezone={timezone}
                    onClose={url.closeContact}
                />
            )}

            <ContactsModals
                organizerId={organizerId}
                modal={modal}
                timezone={timezone}
                filter={filter}
                onOpenModal={setModal}
                onOpenContact={url.openContact}
                onSegmentSaved={url.selectSavedSegment}
            />
        </AudienceShell>
    );
};

export default AudienceContacts;
