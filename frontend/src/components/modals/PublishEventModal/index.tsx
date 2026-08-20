import {ReactNode} from 'react';
import {Anchor, Button, Modal, Skeleton, Text} from '@mantine/core';
import {IconCalendarOff, IconCreditCardOff, IconTicketOff} from '@tabler/icons-react';
import {t} from "@lingui/macro";
import {useNavigate} from "react-router";
import {BouncingEmoji} from "../../common/BouncingEmoji";
import {Event, EventType, Product, ProductPriceType} from "../../../types.ts";
import {useGetEventSettings} from "../../../queries/useGetEventSettings.ts";
import {useGetOrganizer} from "../../../queries/useGetOrganizer.ts";
import {useGetEventProductCategories} from "../../../queries/useGetProductCategories.ts";
import {useGetEventOccurrences} from "../../../queries/useGetEventOccurrences.ts";
import {useUpdateEventStatus} from "../../../mutations/useUpdateEventStatus.ts";
import {showError} from "../../../utilites/notifications.tsx";
import classes from './PublishEventModal.module.scss';

interface PublishEventModalProps {
    opened: boolean;
    onClose: () => void;
    event: Event;
    onSuccess: () => void;
}

interface PublishCheck {
    key: string;
    blocking: boolean;
    icon: ReactNode;
    title: string;
    description: string;
    actionLabel: string;
    actionUrl: string;
    secondaryActionLabel?: string;
    secondaryActionUrl?: string;
}

const productRequiresPayment = (product: Product): boolean => {
    if (product.type === ProductPriceType.Free) {
        return false;
    }
    if (product.type === ProductPriceType.Donation) {
        return true;
    }
    return (product.prices ?? []).some((price) => (price.price ?? 0) > 0);
};

export const PublishEventModal = ({opened, onClose, event, onSuccess}: PublishEventModalProps) => {
    const navigate = useNavigate();
    const eventId = event.id?.toString();
    const organizerId = event.organizer_id ?? event.organizer?.id;
    const isRecurring = event.type === EventType.RECURRING;

    const {data: eventSettings, isFetched: isSettingsFetched} = useGetEventSettings(eventId);
    const {data: organizer, isFetched: isOrganizerFetched} = useGetOrganizer(organizerId);
    const {data: productCategories, isFetched: isProductsFetched} = useGetEventProductCategories(eventId);
    const occurrencesQuery = useGetEventOccurrences(eventId, {pageNumber: 1, perPage: 1}, isRecurring);

    const statusMutation = useUpdateEventStatus();

    const products = productCategories?.data?.flatMap((category) => category.products ?? []) ?? [];
    const hasProducts = products.length > 0;
    const hasPaidProducts = products.some(productRequiresPayment);
    const isStripeEnabled = !!eventSettings?.payment_providers?.includes('STRIPE');
    const isStripeConnected = !!organizer?.stripe_connect_setup_complete;
    const hasOccurrences = (occurrencesQuery.data?.data?.length ?? 0) > 0;

    const checksLoaded = isSettingsFetched
        && isOrganizerFetched
        && isProductsFetched
        && (!isRecurring || occurrencesQuery.isFetched);

    const checks: PublishCheck[] = [];

    if (hasPaidProducts && isStripeEnabled && !isStripeConnected) {
        checks.push({
            key: 'stripe',
            blocking: true,
            icon: <IconCreditCardOff size={18}/>,
            title: t`Connect Stripe to accept payments`,
            description: t`You have paid tickets, but Stripe isn't connected yet, so you can't take payments.`,
            actionLabel: t`Connect Stripe`,
            actionUrl: `/manage/organizer/${organizerId}/settings#payouts`,
            secondaryActionLabel: t`Or enable offline payments and disable Stripe`,
            secondaryActionUrl: `/manage/event/${eventId}/settings#payment-settings`,
        });
    }

    if (!hasProducts) {
        checks.push({
            key: 'tickets',
            blocking: false,
            icon: <IconTicketOff size={18}/>,
            title: t`No tickets to sell`,
            description: t`This event has no tickets or products yet, so attendees won't be able to register.`,
            actionLabel: t`Add tickets`,
            actionUrl: `/manage/event/${eventId}/products#create-product`,
        });
    }

    if (isRecurring && !hasOccurrences) {
        checks.push({
            key: 'occurrences',
            blocking: false,
            icon: <IconCalendarOff size={18}/>,
            title: t`No dates scheduled`,
            description: t`This recurring event has no dates yet, so there's nothing for attendees to book.`,
            actionLabel: t`Add dates`,
            actionUrl: `/manage/event/${eventId}/occurrences`,
        });
    }

    const hasBlocker = checks.some((check) => check.blocking);
    const hasWarning = checks.some((check) => !check.blocking);

    const handleFix = (url: string) => {
        onClose();
        navigate(url);
    };

    const handlePublish = () => {
        statusMutation.mutate({
            eventId,
            status: 'LIVE',
        }, {
            onSuccess,
            onError: (error: any) => {
                showError(error?.response?.data?.message || t`Event status update failed. Please try again later`);
            },
        });
    };

    return (
        <Modal
            opened={opened}
            onClose={onClose}
            centered
            size="md"
            withCloseButton={false}
            className={classes.modal}
        >
            <div className={classes.content}>
                <BouncingEmoji emoji="🚀" size={56}/>

                <Text className={classes.title}>
                    {t`Ready to go live?`}
                </Text>

                <Text className={classes.subtitle}>
                    {t`Publishing makes your event page public and opens it up for registrations.`}
                </Text>

                {!checksLoaded && <Skeleton height={70} radius="md" className={classes.skeleton}/>}

                {checksLoaded && checks.length > 0 && (
                    <div className={classes.checks}>
                        {checks.map((check) => (
                            <div
                                key={check.key}
                                className={`${classes.check} ${check.blocking ? classes.blocking : classes.warning}`}
                            >
                                <div className={classes.checkIcon}>
                                    {check.icon}
                                </div>
                                <div className={classes.checkText}>
                                    <span className={classes.checkTitle}>{check.title}</span>
                                    <span className={classes.checkDescription}>{check.description}</span>
                                    {check.secondaryActionLabel && check.secondaryActionUrl && (
                                        <Anchor
                                            component="button"
                                            type="button"
                                            className={classes.checkSecondaryLink}
                                            onClick={() => handleFix(check.secondaryActionUrl as string)}
                                        >
                                            {check.secondaryActionLabel}
                                        </Anchor>
                                    )}
                                </div>
                                <Button
                                    size="xs"
                                    variant="light"
                                    color={check.blocking ? 'red' : 'yellow'}
                                    className={classes.checkAction}
                                    onClick={() => handleFix(check.actionUrl)}
                                >
                                    {check.actionLabel}
                                </Button>
                            </div>
                        ))}
                    </div>
                )}

                <Button
                    onClick={handlePublish}
                    disabled={!checksLoaded || hasBlocker}
                    loading={statusMutation.isPending}
                    size="md"
                    className={classes.publishButton}
                    data-testid="publish-event-confirm-button"
                >
                    {hasBlocker && t`Fix issues to publish`}
                    {!hasBlocker && (hasWarning ? t`Publish Anyway` : t`Publish Event`)}
                </Button>

                <Button
                    onClick={onClose}
                    variant="subtle"
                    color="gray"
                    className={classes.cancelButton}
                >
                    {t`Cancel`}
                </Button>
            </div>
        </Modal>
    );
};
