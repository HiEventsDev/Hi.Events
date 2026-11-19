import {createPortal} from "react-dom";
import {plural, t} from "@lingui/macro";
import {Avatar, UnstyledButton, VisuallyHidden} from "@mantine/core";
import {IconArrowDown, IconArrowUp, IconSelector} from "@tabler/icons-react";
import {VisibilityState} from "@tanstack/react-table";
import {ColumnVisibilityToggle} from "../../../../../components/common/ColumnVisibilityToggle";
import {TanStackTable, TanStackTableColumn} from "../../../../../components/common/TanStackTable";
import {relativeDate} from "../../../../../utilites/dates.ts";
import {formatPhoneNumber} from "../../../../../utilites/phoneNumber.ts";
import {CrmContact} from "../../../types.ts";
import {contactAvatarColor, contactDisplayName, contactInitials, formatCount, getContactId} from "../../../utilites/format.ts";
import {MarketingStatusBadge} from "../../MarketingStatusBadge";
import {TagList} from "../../TagList";
import {ContactStack, SortDirection, SortOption} from "./ContactStack.tsx";
import classes from "./Contacts.module.scss";

interface ContactsTableProps {
    contacts: CrmContact[];
    isCompact: boolean;
    showTextMessages: boolean;
    sortBy: string;
    sortDirection: SortDirection;
    onSort: (sortBy: string, sortDirection: SortDirection) => void;
    onOpen: (contact: CrmContact) => void;
    selectedIds: ReadonlySet<string>;
    onSelectionChange: (selectedIds: Set<string>) => void;
    columnVisibility: VisibilityState;
    onColumnVisibilityChange: (columnVisibility: VisibilityState) => void;
    columnToggleSlot: HTMLElement | null;
}

const ROW_TAG_LIMIT = 2;
const STACK_TAG_LIMIT = 1;

const CONTACT_CELL = {width: '70%', maxWidth: 0, minWidth: 'clamp(280px, 32cqi, 380px)'};
const TAGS_CELL = {width: '30%', maxWidth: 0, minWidth: 150};
const NUMBER_CELL = {textAlign: 'right' as const};

export const SortHeader = ({label, field, firstDirection, sortBy, sortDirection, onSort}: {
    label: string,
    field: string,
    firstDirection: SortDirection,
    sortBy: string,
    sortDirection: SortDirection,
    onSort: ContactsTableProps['onSort'],
}) => {
    const isActive = sortBy === field;
    const Icon = !isActive ? IconSelector : sortDirection === 'asc' ? IconArrowUp : IconArrowDown;

    return (
        <UnstyledButton
            className={classes.sortHeader}
            data-active={isActive || undefined}
            onClick={() => onSort(field, isActive ? (sortDirection === 'asc' ? 'desc' : 'asc') : firstDirection)}
        >
            {label}
            <Icon size={14} stroke={1.8} aria-hidden/>
            {isActive && (
                <VisuallyHidden>{sortDirection === 'asc' ? t`Sorted ascending` : t`Sorted descending`}</VisuallyHidden>
            )}
        </UnstyledButton>
    );
};

const Count = ({value}: { value: number }) => (
    <span className={classes.figure} data-zero={value === 0 || undefined}>{formatCount(value)}</span>
);


const getContactLabel = (contact: CrmContact) => {
    const name = contactDisplayName(contact);
    return t`Select ${name}`;
};

export const ContactsTable = ({
    contacts,
    isCompact,
    showTextMessages,
    sortBy,
    sortDirection,
    onSort,
    onOpen,
    selectedIds,
    onSelectionChange,
    columnVisibility,
    onColumnVisibilityChange,
    columnToggleSlot,
}: ContactsTableProps) => {
    const sortOptions: SortOption[] = [
        {field: 'last_activity_at', label: t`Last active`, firstDirection: 'desc'},
        {field: 'first_name', label: t`Name`, firstDirection: 'asc'},
        {field: 'events_count', label: t`Events`, firstDirection: 'desc'},
        {field: 'orders_count', label: t`Orders`, firstDirection: 'desc'},
        {field: 'attended_count', label: t`Check-ins`, firstDirection: 'desc'},
        {field: 'first_seen_at', label: t`First seen`, firstDirection: 'desc'},
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
                    const lastActive = contact.last_activity_at ? relativeDate(contact.last_activity_at) : null;

                    return (
                        <>
                            <Avatar size={30} radius="xl" color={contactAvatarColor(contact)} className={classes.avatar}>{contactInitials(contact)}</Avatar>
                            <span className={classes.stackText}>
                                <span className={classes.stackIdentity}>
                                    <UnstyledButton className={classes.stackName} onClick={() => onOpen(contact)}>
                                        {contactDisplayName(contact)}
                                    </UnstyledButton>
                                    <span className={classes.stackSecondary}>{contact.full_name ? contact.email : t`No name yet`}</span>
                                </span>
                                <span className={classes.stackMeta}>
                                    <MarketingStatusBadge status={contact.email_marketing_status}/>
                                    <span className={classes.stackFacts}>
                                        {plural(contact.events_count, {one: '# event', other: '# events'})}
                                        {lastActive && <>{' · '}{t`Last active ${lastActive}`}</>}
                                    </span>
                                    {contact.tags.length > 0 && <TagList tags={contact.tags} limit={STACK_TAG_LIMIT}/>}
                                </span>
                            </span>
                        </>
                    );
                }}
            />
        );
    }

    const sortable = (label: string, field: string, firstDirection: SortDirection = 'desc') => ({
        meta: {label},
        header: () => (
            <SortHeader
                label={label}
                field={field}
                firstDirection={firstDirection}
                sortBy={sortBy}
                sortDirection={sortDirection}
                onSort={onSort}
            />
        ),
    });

    const countColumn = (id: 'events_count' | 'orders_count' | 'attended_count', label: string): TanStackTableColumn<CrmContact> => ({
        id,
        ...sortable(label, id),
        meta: {label, headerStyle: NUMBER_CELL, cellStyle: NUMBER_CELL},
        cell: ({row: {original: contact}}) => <Count value={contact[id]}/>,
    });

    const columns: TanStackTableColumn<CrmContact>[] = [
        {
            id: 'contact',
            enableHiding: false,
            ...sortable(t`Contact`, 'first_name', 'asc'),
            meta: {label: t`Contact`, sticky: 'left', cellStyle: CONTACT_CELL},
            cell: ({row: {original: contact}}) => (
                <span className={classes.identity}>
                    <Avatar size={30} radius="xl" color={contactAvatarColor(contact)} className={classes.avatar}>{contactInitials(contact)}</Avatar>
                    <span className={classes.identityText}>
                        <UnstyledButton className={classes.identityName} onClick={() => onOpen(contact)} title={contactDisplayName(contact)}>
                            {contactDisplayName(contact)}
                        </UnstyledButton>
                        <span title={contact.full_name ? contact.email : undefined}>{contact.full_name ? contact.email : t`No name yet`}</span>
                    </span>
                </span>
            ),
        },
        countColumn('events_count', t`Events`),
        countColumn('orders_count', t`Orders`),
        countColumn('attended_count', t`Check-ins`),
        {
            id: 'last_activity_at',
            ...sortable(t`Last active`, 'last_activity_at'),
            cell: ({row: {original: contact}}) => contact.last_activity_at ? relativeDate(contact.last_activity_at) : '–',
        },
        {
            id: 'first_seen_at',
            ...sortable(t`First seen`, 'first_seen_at'),
            cell: ({row: {original: contact}}) => contact.first_seen_at ? relativeDate(contact.first_seen_at) : '–',
        },
        {
            id: 'marketing',
            header: t`Email status`,
            cell: ({row: {original: contact}}) => <MarketingStatusBadge status={contact.email_marketing_status}/>,
        },
        {
            id: 'phone',
            header: t`Mobile number`,
            cell: ({row: {original: contact}}) => contact.phone ? formatPhoneNumber(contact.phone) : '–',
        },
        ...(showTextMessages ? [{
            id: 'sms',
            header: t`Text message status`,
            cell: ({row: {original: contact}}) => <MarketingStatusBadge status={contact.sms_marketing_status}/>,
        } satisfies TanStackTableColumn<CrmContact>] : []),
        {
            id: 'tags',
            header: t`Tags`,
            meta: {cellStyle: TAGS_CELL},
            cell: ({row: {original: contact}}) => <TagList tags={contact.tags} limit={ROW_TAG_LIMIT}/>,
        },
    ];

    return (
        <TanStackTable
            data={contacts}
            columns={columns}
            enableColumnVisibility
            renderColumnVisibilityToggle={table => columnToggleSlot && createPortal(<ColumnVisibilityToggle table={table}/>, columnToggleSlot)}
            columnVisibility={columnVisibility}
            onColumnVisibilityChange={onColumnVisibilityChange}
            selection={{
                selectedIds,
                onChange: onSelectionChange,
                getRowId: getContactId,
                getRowLabel: getContactLabel,
            }}
            rowProps={contact => ({
                onClick: () => onOpen(contact),
                'data-testid': 'audience-contact-row',
            })}
        />
    );
};
