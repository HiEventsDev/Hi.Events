import {Message, MessageType} from "../../../types.ts";
import classes from './MessageList.module.scss';
import {relativeDate} from "../../../utilites/dates.ts";
import {Card} from "../Card";
import {Anchor, Avatar, Badge} from "@mantine/core";
import {getInitials} from "../../../utilites/helpers.ts";
import {NoResultsSplash} from "../NoResultsSplash";
import {t} from "@lingui/macro";
import {useState} from "react";

interface MessageListProps {
    messages: Message[];
}

const SingleMessage = ({message}: { message: Message }) => {
    const [showFullMessage, setShowFullMessage] = useState(false);

    const typeToDescription = {
        [MessageType.OrderOwnersWithProduct]: t`Order owners with products`,
        [MessageType.IndividualAttendees]: t`Individual attendees`,
        [MessageType.AllAttendees]: t`All attendees`,
        [MessageType.TicketHolders]: t`Ticket holders`,
        [MessageType.OrderOwner]: t`Order owner`,
    }

    return (
        <Card className={classes.message}>
            <div className={classes.avatar}>
                <Avatar color={"grape"}
                        size={40}>{getInitials(message.sent_by_user?.first_name + " " + message.sent_by_user?.last_name)}</Avatar>
            </div>
            <div className={classes.details}>

                <div className={classes.status}>
                    <Badge
                        color={message.status === "SENT" ? "green" : "orange"}
                        variant={"outline"}>
                        {message.status}
                    </Badge>
                </div>
                <div className={classes.date}>
                    <div className={classes.date} title={message.sent_at}>
                        {relativeDate(message.sent_at as string)}
                    </div>
                </div>
                <div className={classes.subject}>{message.subject}</div>
                <div className={classes.type}>
                    {typeToDescription[message.type]}
                </div>
                <div className={classes.content}>
                    {showFullMessage
                        ? <div dangerouslySetInnerHTML={{__html: message.message}}></div>
                        : <div className={classes.preview}>{message.message_preview}</div>}
                </div>
                <Anchor
                    className={classes.read_more}
                    onClick={() => setShowFullMessage(!showFullMessage)}
                >
                    {showFullMessage ? t`Read less` : t`View full message`}
                </Anchor>
            </div>
        </Card>
    );
};

export const MessageList = ({messages}: MessageListProps) => {
    if (messages.length === 0) {
        return <NoResultsSplash
            heading={t`No messages to show`}
            imageHref={'/blank-slate/messages.svg'}
            subHeading={(
                <>
                    <p>
                        {t`You haven't sent any messages yet. You can send messages to all attendees, or to specific product holders.`}
                    </p>
                </>
            )}
        />
    }
    return (
        <div>
            {messages.map((message) => {
                return (
                    <SingleMessage key={message.id} message={message}/>
                )
            })}
        </div>
    )
}
