import {t} from "@lingui/macro";
import {Tooltip, UnstyledButton} from "@mantine/core";
import {TanStackTable, TanStackTableColumn} from "../../../../../components/common/TanStackTable";
import {formatDateWithLocale, relativeDate} from "../../../../../utilites/dates.ts";
import {CrmContact} from "../../../types.ts";
import {contactDisplayName, getContactId} from "../../../utilites/format.ts";
import {suppressionSourceLabel} from "../../../utilites/suppression.ts";
import {MarketingStatusBadge} from "../../MarketingStatusBadge";
import {ContactStack, SortDirection, SortOption} from "./ContactStack.tsx";
import {SortHeader} from "./ContactsTable.tsx";
import classes from "./Contacts.module.scss";

const CONTACT_CELL = {width: '60%', maxWidth: 0, minWidth: 280};
const SOURCE_CELL = {width: '40%', maxWidth: 0, minWidth: 160};

const suppressedSince = (contact: CrmContact) => contact.suppression?.since ?? contact.email_marketing_status_changed_at;

const getContactLabel = (contact: CrmContact) => {
    const email = contact.email;
    return t`Select ${email}`;
};

export const SuppressedTable = ({contacts, isCompact, timezone, sortBy, sortDirection, onSort, onOpen, selectedIds, onSelectionChange}: {
    contacts: CrmContact[],
    isCompact: boolean,
    timezone: string,
    sortBy: string,
    sortDirection: SortDirection,
    onSort: (sortBy: string, sortDirection: SortDirection) => void,
    onOpen: (contact: CrmContact) => void,
    selectedIds: ReadonlySet<string>,
    onSelectionChange: (selectedIds: Set<string>) => void,
}) => {
    const sortOptions: SortOption[] = [
        {field: 'email_marketing_status_changed_at', label: t`Since`, firstDirection: 'desc'},
        {field: 'email', label: t`Email`, firstDirection: 'asc'},
    ];

    if (isCompact) {
        return (
            <ContactStack
                rows={contacts}
                getRowId={getContactId}
                getRowLabel={getContactLabel}
                onOpen={onOpen}
                selectedIds={selectedIds}
                onSelectionChange={onSelectionChange}
                sortOptions={sortOptions}
                sortBy={sortBy}
                sortDirection={sortDirection}
                onSort={onSort}
                renderRow={contact => {
                    const since = suppressedSince(contact);

                    return (
                        <span className={classes.stackText}>
                            <span className={classes.stackIdentity}>
                                <UnstyledButton className={classes.stackName} onClick={() => onOpen(contact)}>{contact.email}</UnstyledButton>
                                {contact.full_name && <span className={classes.stackSecondary}>{contactDisplayName(contact)}</span>}
                            </span>
                            <span className={classes.stackMeta}>
                                {contact.suppression && <MarketingStatusBadge status={contact.suppression.reason}/>}
                                <span className={classes.stackFacts}>
                                    {[since && relativeDate(since), contact.suppression && suppressionSourceLabel(contact.suppression)].filter(Boolean).join(' · ')}
                                </span>
                            </span>
                        </span>
                    );
                }}
            />
        );
    }

    const sortable = ({label, field, firstDirection}: SortOption) => ({
        meta: {label},
        header: () => <SortHeader label={label} field={field} firstDirection={firstDirection} sortBy={sortBy} sortDirection={sortDirection} onSort={onSort}/>,
    });

    const columns: TanStackTableColumn<CrmContact>[] = [
        {
            id: 'contact',
            ...sortable(sortOptions[1]),
            meta: {label: sortOptions[1].label, cellStyle: CONTACT_CELL},
            cell: ({row: {original: contact}}) => (
                <span className={classes.identityText}>
                    <UnstyledButton className={classes.identityName} onClick={() => onOpen(contact)} title={contact.email}>{contact.email}</UnstyledButton>
                    {contact.full_name && <span>{contactDisplayName(contact)}</span>}
                </span>
            ),
        },
        {
            id: 'reason',
            header: t`Reason`,
            cell: ({row: {original: contact}}) => contact.suppression ? <MarketingStatusBadge status={contact.suppression.reason}/> : '–',
        },
        {
            id: 'since',
            ...sortable(sortOptions[0]),
            cell: ({row: {original: contact}}) => {
                const since = suppressedSince(contact);

                return since ? (
                    <Tooltip label={formatDateWithLocale(since, 'fullDateTime', timezone)} openDelay={300}>
                        <span>{relativeDate(since)}</span>
                    </Tooltip>
                ) : '–';
            },
        },
        {
            id: 'source',
            header: t`Source`,
            meta: {cellStyle: SOURCE_CELL},
            cell: ({row: {original: contact}}) => {
                const source = contact.suppression ? suppressionSourceLabel(contact.suppression) : '–';

                return <span className={classes.source} title={source}>{source}</span>;
            },
        },
    ];

    return (
        <TanStackTable
            data={contacts}
            columns={columns}
            selection={{
                selectedIds,
                onChange: onSelectionChange,
                getRowId: getContactId,
                getRowLabel: getContactLabel,
            }}
            rowProps={contact => ({onClick: () => onOpen(contact), 'data-testid': 'audience-contact-row'})}
        />
    );
};
