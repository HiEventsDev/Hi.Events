import {ReactNode} from "react";
import {t} from "@lingui/macro";
import {ActionIcon, Menu, Tooltip} from "@mantine/core";
import {
    IconAlertTriangle,
    IconChartBar,
    IconCheck,
    IconClipboardList,
    IconCopy,
    IconDotsVertical,
    IconPencil,
    IconPlayerPlay,
    IconReceipt,
    IconSend,
    IconShare,
    IconTrash,
    IconUsers,
    IconX,
} from "@tabler/icons-react";
import {EventOccurrence, EventOccurrenceStatus, IdParam} from "../../../../types.ts";

export interface OccurrenceMenuActions {
    eventId: IdParam;
    onEdit: (occurrenceId: number) => void;
    onCancel: (occurrenceId: number) => void;
    onDelete: (occurrenceId: number) => void;
    onNavigate: (path: string) => void;
    onDuplicate?: (occ: EventOccurrence) => void;
    onMessage?: (occurrenceId: number) => void;
    onCheckIn?: (occurrenceId: number) => void;
    onReactivate?: (occ: EventOccurrence) => void;
    onShare?: (occ: EventOccurrence) => void;
}

type ActionGroup = 'primary' | 'secondary' | 'danger';

interface OccurrenceAction {
    key: string;
    icon: ReactNode;
    label: string;
    onClick: () => void;
    group: ActionGroup;
    color?: string;
}

const buildActions = (occ: EventOccurrence, actions: OccurrenceMenuActions): OccurrenceAction[] => {
    const isActive = occ.status === EventOccurrenceStatus.ACTIVE;
    const isCancelled = occ.status === EventOccurrenceStatus.CANCELLED;
    const id = occ.id as number;

    const items: (OccurrenceAction | false)[] = [
        {key: 'edit', icon: <IconPencil size={14}/>, label: t`Edit`, onClick: () => actions.onEdit(id), group: 'primary'},
        !!actions.onDuplicate && {key: 'duplicate', icon: <IconCopy size={14}/>, label: t`Duplicate`, onClick: () => actions.onDuplicate!(occ), group: 'secondary'},
        {key: 'dashboard', icon: <IconChartBar size={14}/>, label: t`Dashboard`, onClick: () => actions.onNavigate(`/manage/event/${actions.eventId}/occurrences/${occ.id}`), group: 'primary'},
        {key: 'attendees', icon: <IconUsers size={14}/>, label: t`Attendees`, onClick: () => actions.onNavigate(`/manage/event/${actions.eventId}/attendees?filterFields[event_occurrence_id][eq]=${occ.id}`), group: 'secondary'},
        {key: 'orders', icon: <IconReceipt size={14}/>, label: t`Orders`, onClick: () => actions.onNavigate(`/manage/event/${actions.eventId}/orders?filterFields[event_occurrence_id][eq]=${occ.id}`), group: 'secondary'},
        !!actions.onMessage && {key: 'message', icon: <IconSend size={14}/>, label: t`Message`, onClick: () => actions.onMessage!(id), group: 'primary'},
        !!actions.onCheckIn && !isCancelled && {key: 'checkin', icon: <IconClipboardList size={14}/>, label: t`Check-In`, onClick: () => actions.onCheckIn!(id), group: 'primary'},
        !!actions.onShare && !isCancelled && {key: 'share', icon: <IconShare size={14}/>, label: t`Share`, onClick: () => actions.onShare!(occ), group: 'primary'},
        isCancelled && !!actions.onReactivate && {key: 'reactivate', icon: <IconPlayerPlay size={14}/>, label: t`Reactivate`, onClick: () => actions.onReactivate!(occ), group: 'primary', color: 'green'},
        isActive && {key: 'cancel', icon: <IconX size={14}/>, label: t`Cancel`, onClick: () => actions.onCancel(id), group: 'danger', color: 'red'},
        {key: 'delete', icon: <IconTrash size={14}/>, label: t`Delete`, onClick: () => actions.onDelete(id), group: 'danger', color: 'red'},
    ];

    return items.filter(Boolean) as OccurrenceAction[];
};

// Vertical dropdown menu (used in table rows and calendar)

interface OccurrenceMenuItemsProps {
    occurrence: EventOccurrence;
    actions: OccurrenceMenuActions;
}

export const OccurrenceMenuItems = ({occurrence, actions}: OccurrenceMenuItemsProps) => {
    const allActions = buildActions(occurrence, actions);
    const primary = allActions.filter(a => a.group === 'primary');
    const secondary = allActions.filter(a => a.group === 'secondary');
    const danger = allActions.filter(a => a.group === 'danger');

    return (
        <>
            <Menu.Label>{t`Manage`}</Menu.Label>
            {[...primary, ...secondary].map(action => (
                <Menu.Item
                    key={action.key}
                    leftSection={action.icon}
                    onClick={action.onClick}
                    color={action.color}
                >
                    {action.label}
                </Menu.Item>
            ))}
            {danger.length > 0 && (
                <>
                    <Menu.Divider/>
                    <Menu.Label>{t`Danger zone`}</Menu.Label>
                    {danger.map(action => (
                        <Menu.Item
                            key={action.key}
                            leftSection={action.icon}
                            onClick={action.onClick}
                            color={action.color}
                        >
                            {action.label}
                        </Menu.Item>
                    ))}
                </>
            )}
        </>
    );
};

// Horizontal action bar (used in slideout / manage modal)

interface OccurrenceActionBarProps {
    occurrence: EventOccurrence;
    actions: OccurrenceMenuActions;
}

const actionButtonStyle = {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
    padding: '6px 10px',
    borderRadius: 'var(--mantine-radius-sm)',
    fontSize: 'var(--mantine-font-size-xs)',
    fontWeight: 500,
    cursor: 'pointer',
    border: 'none',
    background: 'var(--mantine-color-gray-1)',
    color: 'var(--mantine-color-text)',
    transition: 'background 0.1s',
    whiteSpace: 'nowrap' as const,
};

export const OccurrenceActionBar = ({occurrence, actions}: OccurrenceActionBarProps) => {
    const allActions = buildActions(occurrence, actions);
    const primary = allActions.filter(a => a.group === 'primary');
    const overflow = allActions.filter(a => a.group === 'secondary' || a.group === 'danger');
    const overflowSecondary = overflow.filter(a => a.group === 'secondary');
    const overflowDanger = overflow.filter(a => a.group === 'danger');

    const getStyle = (action: OccurrenceAction) => {
        if (action.color === 'green') {
            return {style: {...actionButtonStyle, background: 'var(--mantine-color-green-0)', color: 'var(--mantine-color-green-7)'}, hoverBg: 'var(--mantine-color-green-1)', restBg: 'var(--mantine-color-green-0)'};
        }
        return {style: actionButtonStyle, hoverBg: 'var(--mantine-color-gray-2)', restBg: 'var(--mantine-color-gray-1)'};
    };

    return (
        <div style={{display: 'flex', flexWrap: 'wrap', gap: 6, alignItems: 'center'}}>
            {primary.map(action => {
                const {style, hoverBg, restBg} = getStyle(action);
                return (
                    <button
                        key={action.key}
                        type="button"
                        style={style}
                        onClick={action.onClick}
                        onMouseEnter={e => (e.currentTarget.style.background = hoverBg)}
                        onMouseLeave={e => (e.currentTarget.style.background = restBg)}
                    >
                        {action.icon} {action.label}
                    </button>
                );
            })}

            {overflow.length > 0 && (
                <Menu shadow="md" width={200} position="bottom-end">
                    <Menu.Target>
                        <Tooltip label={t`More actions`} withArrow>
                            <ActionIcon variant="subtle" color="gray" size="sm">
                                <IconDotsVertical size={16}/>
                            </ActionIcon>
                        </Tooltip>
                    </Menu.Target>
                    <Menu.Dropdown>
                        {overflowSecondary.map(action => (
                            <Menu.Item
                                key={action.key}
                                leftSection={action.icon}
                                onClick={action.onClick}
                            >
                                {action.label}
                            </Menu.Item>
                        ))}
                        {overflowDanger.length > 0 && overflowSecondary.length > 0 && <Menu.Divider/>}
                        {overflowDanger.map(action => (
                            <Menu.Item
                                key={action.key}
                                leftSection={action.icon}
                                onClick={action.onClick}
                                color={action.color}
                            >
                                {action.label}
                            </Menu.Item>
                        ))}
                    </Menu.Dropdown>
                </Menu>
            )}
        </div>
    );
};

// Shared utilities

export const statusLabel = (status?: EventOccurrenceStatus) => {
    switch (status) {
        case EventOccurrenceStatus.ACTIVE:
            return t`Active`;
        case EventOccurrenceStatus.CANCELLED:
            return t`Cancelled`;
        case EventOccurrenceStatus.SOLD_OUT:
            return t`Sold Out`;
        default:
            return status || '';
    }
};

export const StatusIcon = ({status}: {status?: EventOccurrenceStatus}) => {
    switch (status) {
        case EventOccurrenceStatus.ACTIVE:
            return <IconCheck size={14}/>;
        case EventOccurrenceStatus.CANCELLED:
            return <IconX size={14}/>;
        case EventOccurrenceStatus.SOLD_OUT:
            return <IconAlertTriangle size={14}/>;
        default:
            return null;
    }
};
