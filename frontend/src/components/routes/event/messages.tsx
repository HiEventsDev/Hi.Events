import {useParams} from "react-router";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useDisclosure} from "@mantine/hooks";
import {MessageRecipientsModal} from "../../modals/MessageRecipientsModal";
import {Pagination} from "../../common/Pagination";
import {ActionIcon, Avatar, Badge, Button, CloseButton, Loader, TextInput, Tooltip} from "@mantine/core";
import {IconArrowLeft, IconClock, IconMail, IconSearch, IconSend, IconX} from "@tabler/icons-react";
import {useFilterQueryParamSync} from "../../../hooks/useFilterQueryParamSync.ts";
import {IdParam, Message, MessageType, QueryFilters} from "../../../types.ts";
import {useGetEventMessages} from "../../../queries/useGetEventMessages.ts";
import {MessageList, statusBadgeColor, typeLabel} from "../../common/MessageList";
import {NoResultsSplash} from "../../common/NoResultsSplash";
import {SendMessageModal} from "../../modals/SendMessageModal";
import {t} from "@lingui/macro";
import {useCallback, useEffect, useRef, useState} from "react";
import {dateToBrowserTz, relativeDate} from "../../../utilites/dates.ts";
import {getInitials} from "../../../utilites/helpers.ts";
import {useCancelMessage} from "../../../mutations/useCancelMessage.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import classes from "./messages.module.scss";

const MessagePreview = ({message, eventId, onBack, eventTimezone}: { message: Message; eventId: string; onBack: () => void; eventTimezone: string }) => {
    const [recipientsOpen, {open: openRecipients, close: closeRecipients}] = useDisclosure(false);
    const cancelMutation = useCancelMessage();
    const senderName = message.sent_by_user
        ? `${message.sent_by_user.first_name} ${message.sent_by_user.last_name}`
        : t`Unknown`;
    const displayTimestamp = (message.status === 'SCHEDULED' || message.status === 'CANCELLED')
        ? (message.scheduled_at ?? message.sent_at ?? message.created_at)
        : (message.sent_at ?? message.created_at ?? message.scheduled_at);

    const handleCancel = () => {
        if (!window.confirm(t`Are you sure you want to cancel this scheduled message?`)) {
            return;
        }
        cancelMutation.mutate({
            eventId: eventId as IdParam,
            messageId: message.id as IdParam,
        }, {
            onSuccess: () => showSuccess(t`Message cancelled`),
            onError: () => showError(t`Failed to cancel message`),
        });
    };

    return (
        <>
            <div className={classes.previewHeader}>
                <div className={classes.previewHeaderTop}>
                    <ActionIcon
                        variant="subtle"
                        color="gray"
                        className={classes.backButton}
                        onClick={onBack}
                        title={t`Back to messages`}
                    >
                        <IconArrowLeft size={18}/>
                    </ActionIcon>
                    <div className={classes.previewHeaderRight}>
                        {message.status === 'SCHEDULED' && message.scheduled_at ? (
                            <div className={classes.scheduledInfo} title={dateToBrowserTz(message.scheduled_at, eventTimezone)}>
                                <IconClock size={14}/>
                                {t`Scheduled`} {relativeDate(message.scheduled_at)}
                            </div>
                        ) : (
                            <span className={classes.previewDate} title={displayTimestamp ? dateToBrowserTz(displayTimestamp, eventTimezone) : undefined}>
                                {displayTimestamp ? relativeDate(displayTimestamp) : t`Unknown`}
                            </span>
                        )}
                        <Badge size="sm" color={statusBadgeColor(message.status)} variant="outline">
                            {message.status}
                        </Badge>
                        {message.status === 'SCHEDULED' && (
                            <Button
                                variant="light"
                                color="red"
                                size="compact-xs"
                                onClick={handleCancel}
                                loading={cancelMutation.isPending}
                            >
                                <IconX size={12}/> {t`Cancel`}
                            </Button>
                        )}
                    </div>
                </div>
                <div className={classes.previewSubject}>{message.subject}</div>
                <div className={classes.previewMeta}>
                    <Avatar color="grape" size={36} radius="xl">
                        {getInitials(senderName)}
                    </Avatar>
                    <div className={classes.previewSenderInfo}>
                        <div className={classes.previewSenderName}>{senderName}</div>
                        <div className={classes.previewRecipientType}>
                            {t`To`}: {message.status === 'SENT' || message.status === 'FAILED' ? (
                                <span className={classes.recipientLink} onClick={openRecipients} role="button" tabIndex={0} onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openRecipients(); } }}>{typeLabel(message.type)}</span>
                            ) : (
                                <Tooltip label={t`Recipients are available after the message is sent`} withArrow>
                                    <span className={classes.recipientLinkDisabled}>{typeLabel(message.type)}</span>
                                </Tooltip>
                            )}
                        </div>
                    </div>
                </div>
            </div>
            <div className={classes.previewBody}>
                <div className={classes.previewCard}>
                    <div
                        className={classes.previewContent}
                        dangerouslySetInnerHTML={{__html: message.message}}
                    />
                </div>
            </div>
            {recipientsOpen && (
                <MessageRecipientsModal
                    eventId={eventId}
                    messageId={message.id as IdParam}
                    onClose={closeRecipients}
                />
            )}
        </>
    );
};

export const Messages = () => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const [searchParams, setSearchParam] = useFilterQueryParamSync();
    const messagesQuery = useGetEventMessages(eventId, searchParams as QueryFilters);
    const messages = messagesQuery?.data?.data;
    const pagination = messagesQuery?.data?.meta;
    const [sendModalOpen, {open: openSendModal, close: closeSendModal}] = useDisclosure(false);
    const [selectedMessage, setSelectedMessage] = useState<Message | null>(null);
    const [searchValue, setSearchValue] = useState(searchParams.query || '');
    const [mobileShowPreview, setMobileShowPreview] = useState(false);
    const prevMessagesRef = useRef<Message[] | undefined>(undefined);
    const listBodyRef = useRef<HTMLDivElement>(null);
    const searchTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        return () => {
            if (searchTimerRef.current) clearTimeout(searchTimerRef.current);
        };
    }, []);

    useEffect(() => {
        if (!messages) return;

        const messagesChanged = messages !== prevMessagesRef.current;
        prevMessagesRef.current = messages;

        if (!messagesChanged) return;

        if (selectedMessage) {
            const updated = messages.find(m => m.id === selectedMessage.id);
            if (updated) {
                setSelectedMessage(updated);
                return;
            }
        }

        setSelectedMessage(messages.length > 0 ? messages[0] : null);
    }, [messages, selectedMessage]);

    const handleSelectMessage = useCallback((message: Message) => {
        setSelectedMessage(message);
        setMobileShowPreview(true);
    }, []);

    const handleBackToList = useCallback(() => {
        setMobileShowPreview(false);
    }, []);

    const handleSearch = useCallback((value: string) => {
        setSearchValue(value);
        if (searchTimerRef.current) {
            clearTimeout(searchTimerRef.current);
        }
        searchTimerRef.current = setTimeout(() => {
            setSearchParam({query: value, pageNumber: 1});
        }, 500);
    }, [setSearchParam]);

    const handleClearSearch = useCallback(() => {
        setSearchValue('');
        if (searchTimerRef.current) {
            clearTimeout(searchTimerRef.current);
        }
        setSearchParam({query: '', pageNumber: 1});
    }, [setSearchParam]);

    const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
        if (!messages?.length || !selectedMessage) return;
        const currentIndex = messages.findIndex(m => m.id === selectedMessage.id);
        if (currentIndex === -1) return;

        let nextIndex: number | null = null;
        if (e.key === 'ArrowDown' && currentIndex < messages.length - 1) {
            nextIndex = currentIndex + 1;
        } else if (e.key === 'ArrowUp' && currentIndex > 0) {
            nextIndex = currentIndex - 1;
        }

        if (nextIndex !== null) {
            e.preventDefault();
            const nextMessage = messages[nextIndex];
            setSelectedMessage(nextMessage);
            setMobileShowPreview(true);

            const listEl = listBodyRef.current;
            if (listEl) {
                const items = listEl.querySelectorAll('[role="option"]');
                items[nextIndex]?.scrollIntoView({block: 'nearest'});
            }
        }
    }, [messages, selectedMessage]);

    const isLoading = !messages || !event;
    const totalMessages = pagination?.total;

    return (
        <>
            <div className={`${classes.mailLayout} ${mobileShowPreview ? classes.mobilePreviewActive : ''}`}>
                <div className={classes.listPanel}>
                    <div className={classes.listHeader}>
                        <div className={classes.listHeaderLeft}>
                            <h1 className={classes.listHeading}>{t`Messages`}</h1>
                            {!isLoading && totalMessages !== undefined && (
                                <span className={classes.messageCount}>{totalMessages}</span>
                            )}
                        </div>
                        <Button
                            variant="filled"
                            size="compact-sm"
                            onClick={openSendModal}
                            leftSection={<IconSend size={14}/>}
                        >
                            {t`Compose`}
                        </Button>
                    </div>

                    <div className={classes.searchBar}>
                        <TextInput
                            placeholder={t`Search messages...`}
                            leftSection={<IconSearch size={16}/>}
                            rightSection={searchValue ? (
                                <CloseButton size="sm" onClick={handleClearSearch}/>
                            ) : null}
                            size="sm"
                            value={searchValue}
                            onChange={(e) => handleSearch(e.currentTarget.value)}
                        />
                    </div>

                    {/* eslint-disable-next-line jsx-a11y/no-static-element-interactions */}
                    <div className={classes.listBody} ref={listBodyRef} onKeyDown={handleKeyDown}>
                        {isLoading && (
                            <div className={classes.loadingState}>
                                <Loader size="sm"/>
                            </div>
                        )}

                        {!isLoading && messages.length === 0 && (
                            <div className={classes.ghostList}>
                                {[...Array(5)].map((_, i) => (
                                    <div key={i} className={classes.ghostRow}>
                                        <div className={classes.ghostAvatar}/>
                                        <div className={classes.ghostLines}>
                                            <div className={classes.ghostLineShort}/>
                                            <div className={classes.ghostLineLong}/>
                                            <div className={classes.ghostLineMed}/>
                                        </div>
                                    </div>
                                ))}
                                <div className={classes.ghostCta}>
                                    <p>{t`Your messages will appear here`}</p>
                                    <Button
                                        variant="light"
                                        size="compact-sm"
                                        onClick={openSendModal}
                                        leftSection={<IconSend size={14}/>}
                                    >
                                        {t`Compose`}
                                    </Button>
                                </div>
                            </div>
                        )}

                        {!isLoading && messages.length > 0 && (
                            <MessageList
                                messages={messages}
                                selectedId={selectedMessage?.id}
                                onSelect={handleSelectMessage}
                            />
                        )}
                    </div>

                    {!!messages?.length && pagination && Number(pagination.last_page) > 1 && (
                        <div className={classes.listFooter}>
                            <Pagination
                                value={searchParams.pageNumber}
                                onChange={(value) => setSearchParam({pageNumber: value})}
                                total={Number(pagination.last_page)}
                                size="sm"
                                marginTop={0}
                            />
                        </div>
                    )}
                </div>

                <div className={classes.previewPanel}>
                    {selectedMessage ? (
                        <MessagePreview
                            message={selectedMessage}
                            eventId={eventId as string}
                            onBack={handleBackToList}
                            eventTimezone={event?.timezone ?? 'UTC'}
                        />
                    ) : (
                        <div className={classes.previewEmpty}>
                            {!isLoading && messages?.length === 0 ? (
                                <NoResultsSplash
                                    heading={t`No messages to show`}
                                    imageHref="/blank-slate/messages.svg"
                                    subHeading={
                                        <p>{t`Send emails to attendees, ticket holders, or order owners. Messages can be sent immediately or scheduled for later.`}</p>
                                    }
                                >
                                    <Button
                                        variant="filled"
                                        size="sm"
                                        onClick={openSendModal}
                                        leftSection={<IconSend size={16}/>}
                                        mt={8}
                                    >
                                        {t`Send your first message`}
                                    </Button>
                                </NoResultsSplash>
                            ) : (
                                <>
                                    <IconMail size={48} stroke={1.2}/>
                                    <p>{t`Select a message to view its contents`}</p>
                                </>
                            )}
                        </div>
                    )}
                </div>
            </div>

            {sendModalOpen && <SendMessageModal messageType={MessageType.OrderOwner} onClose={closeSendModal}/>}
        </>
    );
};

export default Messages;
