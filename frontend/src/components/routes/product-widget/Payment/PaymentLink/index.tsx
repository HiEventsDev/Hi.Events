import {useState} from "react";
import {t} from "@lingui/macro";
import {Button, CopyButton as MantineCopyButton, Text, TextInput} from "@mantine/core";
import {Modal, ModalFooter} from "../../../../common/Modal";
import {
    IconCheck,
    IconClockHour4,
    IconCopy,
    IconCreditCard,
    IconLink,
    IconMail,
    IconMailForward,
    IconShare,
} from "@tabler/icons-react";
import {Event, Order} from "../../../../../types.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import {dateToBrowserTz} from "../../../../../utilites/dates.ts";
import {useCreateOrderPaymentLinkPublic} from "../../../../../mutations/useCreateOrderPaymentLinkPublic.ts";
import {showError} from "../../../../../utilites/notifications.tsx";
import classes from "./PaymentLink.module.scss";

const formatHoldDuration = (hours: number): string => {
    if (hours % 24 === 0) {
        const days = hours / 24;
        return days === 1 ? t`1 day` : t`${days} days`;
    }

    return hours === 1 ? t`1 hour` : t`${hours} hours`;
};

const payByLabel = (order: Order, event: Event): string => dateToBrowserTz(order.reserved_until, event.timezone);

const canShareNatively = (): boolean => typeof navigator !== 'undefined' && typeof navigator.share === 'function';

interface ShareLinkProps {
    url: string;
    subject: string;
    message: string;
    footnote: string;
}

const ShareLink = ({url, subject, message, footnote}: ShareLinkProps) => {
    const mailto = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(`${message}\n\n${url}`)}`;

    const handleNativeShare = async () => {
        try {
            await navigator.share({title: subject, text: message, url});
        } catch {
            return;
        }
    };

    return (
        <div>
            <TextInput
                className={classes.linkField}
                label={t`Payment link`}
                value={url}
                readOnly
                onFocus={(e) => e.currentTarget.select()}
            />
            <div className={classes.shareActions}>
                <MantineCopyButton value={url} timeout={2000}>
                    {({copied, copy}) => (
                        <Button
                            onClick={copy}
                            color={copied ? 'teal' : undefined}
                            leftSection={copied ? <IconCheck size={16}/> : <IconCopy size={16}/>}
                        >
                            {copied ? t`Copied` : t`Copy link`}
                        </Button>
                    )}
                </MantineCopyButton>
                {canShareNatively() ? (
                    <Button variant="default" leftSection={<IconShare size={16}/>} onClick={handleNativeShare}>
                        {t`Share`}
                    </Button>
                ) : (
                    <Button variant="default" component="a" href={mailto} leftSection={<IconMail size={16}/>}>
                        {t`Email it`}
                    </Button>
                )}
            </div>
            <Text className={classes.footnote}>{footnote}</Text>
        </div>
    );
};

const PaymentLinkShare = ({order, event}: { order: Order; event: Event }) => {
    const amount = formatCurrency(order.total_gross, order.currency);
    const payBy = payByLabel(order, event);

    return (
        <ShareLink
            url={order.payment_link_url as string}
            subject={t`Payment request: ${event.title}`}
            message={t`Please pay ${amount} for my registration for ${event.title} by ${payBy}. You can see the order and pay here:`}
            footnote={t`We've also emailed this link to ${order.email}. Anyone with the link can view and pay for this order, so only share it with the person paying. It stops working on ${payBy}.`}
        />
    );
};

export const SharePaymentDetailsModal = ({opened, onClose, order, event}: PaymentLinkModalProps) => {
    const amount = formatCurrency(order.total_gross, order.currency);

    return (
        <Modal opened={opened} onClose={onClose} heading={t`Share the payment details`} centered size="md">
            <Text size="sm" mt="xs" mb="md">
                {event.settings?.offline_payment_reporting_enabled
                    ? t`Send this link to whoever is paying, such as your finance team. They'll see the amount due, the payment reference and how to pay, and can tell the organizer once they've paid. Your tickets aren't shown to them.`
                    : t`Send this link to whoever is paying, such as your finance team. They'll see the amount due, the payment reference and how to pay. Your tickets aren't shown to them.`}
            </Text>
            {order.payment_link_url && (
                <ShareLink
                    url={order.payment_link_url}
                    subject={t`Payment details: ${event.title}`}
                    message={t`Please pay ${amount} for my registration for ${event.title}. The payment details and reference are here:`}
                    footnote={t`Anyone with this link can see the payment details for this order, so only share it with the person paying.`}
                />
            )}
            <ModalFooter>
                <Button onClick={onClose} data-testid="share-payment-details-done-button">{t`Done`}</Button>
            </ModalFooter>
        </Modal>
    );
};

export const SharePaymentDetailsEntryPoint = ({order, event, onReady}: {
    order: Order;
    event: Event;
    onReady: () => void;
}) => {
    const mutation = useCreateOrderPaymentLinkPublic();

    const handleClick = () => {
        if (order.payment_link_url) {
            onReady();
            return;
        }

        mutation.mutate({eventId: event.id, orderShortId: order.short_id}, {
            onSuccess: onReady,
            onError: (error: any) => {
                showError(error?.response?.data?.message || t`We couldn't create a payment link. Please try again.`);
            },
        });
    };

    return (
        <div className={classes.entryPoint}>
            <Button
                variant="subtle"
                size="sm"
                h="auto"
                py={8}
                leftSection={<IconShare size={16}/>}
                loading={mutation.isPending}
                onClick={handleClick}
                styles={{label: {whiteSpace: 'normal', textAlign: 'center', lineHeight: 1.4}}}
                data-testid="share-payment-details-button"
            >
                {t`Someone else paying? Share the payment details`}
            </Button>
        </div>
    );
};

interface PaymentLinkModalProps {
    opened: boolean;
    onClose: () => void;
    order: Order;
    event: Event;
}

export const PaymentLinkModal = ({opened, onClose, order, event}: PaymentLinkModalProps) => {
    const mutation = useCreateOrderPaymentLinkPublic();
    const hasLink = !!order.payment_link_url;
    const holdHours = event.settings?.payment_link_expiry_hours ?? 72;

    const handleCreate = () => {
        mutation.mutate({eventId: event.id, orderShortId: order.short_id}, {
            onError: (error: any) => {
                showError(error?.response?.data?.message || t`We couldn't create a payment link. Please try again.`);
            },
        });
    };

    return (
        <Modal
            opened={opened}
            onClose={onClose}
            heading={hasLink ? t`Your payment link is ready` : t`Send a payment link`}
            centered
            size="md"
        >
            {hasLink ? (
                <>
                    <PaymentLinkShare order={order} event={event}/>
                    <ModalFooter>
                        <Button onClick={onClose} data-testid="payment-link-done-button">{t`Done`}</Button>
                    </ModalFooter>
                </>
            ) : (
                <>
                    <Text size="sm" mt="xs">
                        {t`Use this if someone else, such as your finance team, is paying for your order.`}
                    </Text>
                    <ul className={classes.points}>
                        <li className={classes.point}>
                            <IconClockHour4 size={18}/>
                            <span>{t`Your places are held for up to ${formatHoldDuration(holdHours)} while you wait for payment. If nobody pays in time, they are released.`}</span>
                        </li>
                        <li className={classes.point}>
                            <IconCreditCard size={18}/>
                            <span>{t`The person paying sees your order and the amount due, then pays using any available payment method. They can't change your registration.`}</span>
                        </li>
                        <li className={classes.point}>
                            <IconMailForward size={18}/>
                            <span>{t`We'll email you the link too, and send your order confirmation as soon as they complete checkout.`}</span>
                        </li>
                    </ul>
                    <ModalFooter>
                        <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                        <Button
                            onClick={handleCreate}
                            loading={mutation.isPending}
                            leftSection={<IconLink size={16}/>}
                            data-testid="create-payment-link-button"
                        >
                            {t`Create payment link`}
                        </Button>
                    </ModalFooter>
                </>
            )}
        </Modal>
    );
};

export const PaymentLinkEntryPoint = ({onClick}: { onClick: () => void }) => (
    <div className={classes.entryPoint}>
        <Button
            variant="subtle"
            size="sm"
            h="auto"
            py={8}
            leftSection={<IconLink size={16}/>}
            onClick={onClick}
            styles={{label: {whiteSpace: 'normal', textAlign: 'center', lineHeight: 1.4}}}
            data-testid="open-payment-link-modal"
        >
            {t`Someone else paying? Send them a payment link`}
        </Button>
    </div>
);

export const PaymentLinkActiveNotice = ({order, event, onShareClick}: {
    order: Order;
    event: Event;
    onShareClick: () => void;
}) => (
    <div className={classes.notice}>
        <span className={classes.noticeIcon}><IconLink size={18}/></span>
        <div className={classes.noticeBody}>
            <div className={classes.noticeTitle}>{t`Payment link created`}</div>
            <div className={classes.noticeText} suppressHydrationWarning>
                {t`Your places are held until ${payByLabel(order, event)}. Whoever has the link can pay, or you can still pay yourself below.`}
            </div>
        </div>
        <div className={classes.noticeAction}>
            <Button variant="default" size="sm" leftSection={<IconShare size={16}/>} onClick={onShareClick}>
                {t`Share link`}
            </Button>
        </div>
    </div>
);

export const PaymentRequestNotice = ({order, event}: { order: Order; event: Event }) => {
    const name = [order.first_name, order.last_name].filter(Boolean).join(' ');
    const amount = formatCurrency(order.total_gross, order.currency);

    return (
        <div className={classes.notice}>
            <span className={classes.noticeIcon}><IconCreditCard size={18}/></span>
            <div className={classes.noticeBody}>
                <div className={classes.noticeTitle}>
                    {name ? t`${name} has asked you to pay for their order` : t`You've been asked to pay for this order`}
                </div>
                <div className={classes.noticeText} suppressHydrationWarning>
                    {t`The amount due is ${amount}. Please pay by ${payByLabel(order, event)}, after which the places are released. Once you pay, the confirmation is emailed to ${order.email}.`}
                </div>
            </div>
        </div>
    );
};

export const usePaymentLinkModal = () => {
    const [opened, setOpened] = useState(false);

    return {
        opened,
        open: () => setOpened(true),
        close: () => setOpened(false),
    };
};
