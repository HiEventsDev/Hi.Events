import {IdParam, Message, MessageType} from "../../../types.ts";
import classes from './MessageList.module.scss';
import {relativeDate} from "../../../utilites/dates.ts";
import {Avatar, Badge} from "@mantine/core";
import {getInitials} from "../../../utilites/helpers.ts";
import {t} from "@lingui/macro";

interface MessageListProps {
    messages: Message[];
    selectedId?: IdParam;
    onSelect: (message: Message) => void;
}

export const statusBadgeColor = (status?: string) => {
    switch (status) {
        case 'SENT': return 'green';
        case 'PROCESSING': return 'orange';
        case 'SCHEDULED': return 'blue';
        case 'CANCELLED': return 'gray';
        case 'FAILED': return 'red';
        default: return 'orange';
    }
};

export const typeLabel = (type: MessageType) => {
    const map: Record<string, string> = {
        [MessageType.OrderOwnersWithProduct]: t`Order owners with products`,
        [MessageType.IndividualAttendees]: t`Individual attendees`,
        [MessageType.AllAttendees]: t`All attendees`,
        [MessageType.TicketHolders]: t`Ticket holders`,
        [MessageType.OrderOwner]: t`Order owner`,
    };
    return map[type] || type;
};

const MessageItem = ({message, isSelected, onSelect}: {
    message: Message;
    isSelected: boolean;
    onSelect: () => void;
}) => {
    const isCancelled = message.status === 'CANCELLED';
    const senderName = message.sent_by_user
        ? `${message.sent_by_user.first_name} ${message.sent_by_user.last_name}`
        : t`Unknown`;

    const displayTimestamp = (message.status === 'SCHEDULED' || message.status === 'CANCELLED')
        ? (message.scheduled_at ?? message.sent_at ?? message.created_at)
        : (message.sent_at ?? message.created_at ?? message.scheduled_at);
    const displayDate = displayTimestamp ? relativeDate(displayTimestamp) : t`Unknown`;

    return (
        <div
            className={`${classes.messageItem} ${isSelected ? classes.selected : ''} ${isCancelled ? classes.cancelled : ''}`}
            onClick={onSelect}
            role="option"
            aria-selected={isSelected}
            tabIndex={0}
            onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onSelect();
                }
            }}
        >
            <Avatar color="grape" size={36} radius="xl">
                {getInitials(senderName)}
            </Avatar>
            <div className={classes.itemContent}>
                <div className={classes.itemTopRow}>
                    <span className={classes.sender}>{senderName}</span>
                    <span className={classes.date}>{displayDate}</span>
                </div>
                <div className={classes.subject}>{message.subject}</div>
                <div className={classes.preview}>{message.message_preview}</div>
                <div className={classes.itemBottomRow}>
                    <Badge size="xs" color={statusBadgeColor(message.status)} variant="outline">
                        {message.status}
                    </Badge>
                    <span className={classes.typeLabel}>{typeLabel(message.type)}</span>
                </div>
            </div>
        </div>
    );
};

export const MessageList = ({messages, selectedId, onSelect}: MessageListProps) => {
    return (
        <div className={classes.listContainer} role="listbox">
            {messages.map((message) => (
                <MessageItem
                    key={message.id}
                    message={message}
                    isSelected={message.id === selectedId}
                    onSelect={() => onSelect(message)}
                />
            ))}
        </div>
    );
};
