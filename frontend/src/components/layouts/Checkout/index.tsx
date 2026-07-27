import {Outlet, useBlocker, useLocation, useNavigate, useParams, useSearchParams} from "react-router";
import classes from './Checkout.module.scss';
import {useGetOrderPublic} from "../../../queries/useGetOrderPublic.ts";
import {t} from "@lingui/macro";
import {Countdown} from "../../common/Countdown";
import {ActionIcon, Button, Group, Modal, Tooltip} from "@mantine/core";
import {IconArrowLeft, IconPrinter, IconReceipt} from "@tabler/icons-react";
import {eventHomepagePath, eventHomepageUrl} from "../../../utilites/urlHelper.ts";
import {ShareComponent} from "../../common/ShareIcon";
import {AddToEventCalendarButton} from "../../common/AddEventToCalendarButton";
import {ProgressStepper} from "../../common/ProgressStepper";
import {useMediaQuery} from "@mantine/hooks";
import React, {useCallback, useEffect, useMemo, useState} from "react";
import {getEmbedMode, getParentOrigin} from "../../../utilites/iframeResize.ts";
import {Invoice} from "../../../types.ts";
import {orderClientPublic} from "../../../api/order.client.ts";
import {downloadBinary} from "../../../utilites/download.ts";
import {withLoadingNotification} from "../../../utilites/withLoadingNotification.tsx";
import {useAbandonOrderPublic} from "../../../mutations/useAbandonOrderPublic.ts";
import {showError, showInfo} from "../../../utilites/notifications.tsx";
import {isDateInFuture, utcDateToEpochMs} from "../../../utilites/dates.ts";
import {getCheckoutSessionIdentifier} from "../../../utilites/checkoutSession.ts";
import {PoweredByFooter} from "../../common/PoweredByFooter";
import {detectMode} from "../../../utilites/themeUtils.ts";
import {CheckoutThemeProvider} from "./CheckoutThemeProvider.tsx";
import {useOrganizerTrackingPixels} from "../../../hooks/useOrganizerTrackingPixels";
import {trackPixelEvent, hasActivePixels} from "../../../utilites/trackingPixels";
import {CookieConsentBanner} from "../../common/CookieConsentBanner";
import {useGetEventPublic} from "../../../queries/useGetEventPublic.ts";

const DEFAULT_ACCENT = '#8b5cf6';

const Checkout = () => {
    const {eventId, orderShortId} = useParams();
    const {data: order, isError: isOrderError} = useGetOrderPublic(eventId, orderShortId, ['event']);
    const event = order?.event;
    const orderOccurrenceIds = Array.from(new Set(
        (order?.order_items ?? []).map(item => item.event_occurrence_id).filter((id): id is number => id != null)
    ));
    const orderOccurrenceId = orderOccurrenceIds.length === 1 ? orderOccurrenceIds[0] : null;
    const {data: publicEvent} = useGetEventPublic(eventId, !!eventId, false, null, orderOccurrenceId);
    const navigate = useNavigate();
    const location = useLocation();
    const [searchParams] = useSearchParams();
    const isModal = useMemo(() => {
        const fromUrl = searchParams.get('embed') === 'modal';
        const cached = getEmbedMode() === 'modal';
        return fromUrl || cached;
    }, [searchParams]);
    const orderIsCompleted = order?.status === 'COMPLETED';
    const orderIsReserved = order?.status === 'RESERVED';
    const orderIsAwaitingOfflinePayment = order?.status === 'AWAITING_OFFLINE_PAYMENT';
    const isMobile = useMediaQuery('(max-width: 768px)');
    const [isExpired, setIsExpired] = useState(false);
    const orderHasAttendees = order?.attendees && order.attendees.length > 0;
    const [showAbandonDialog, setShowAbandonDialog] = useState(false);
    const [pendingNavigation, setPendingNavigation] = useState<string | null>(null);
    const [isAbandoning, setIsAbandoning] = useState(false);
    const [pendingClose, setPendingClose] = useState(false);
    const abandonOrderMutation = useAbandonOrderPublic();

    const postToParent = useCallback((type: string, data?: Record<string, unknown>) => {
        if (typeof window === 'undefined') return;
        try {
            window.parent.postMessage({type, ...(data ?? {})}, getParentOrigin() || '*');
        } catch (e) {
            /* noop */
        }
    }, []);

    const closeModal = useCallback(() => postToParent('hievents:close-checkout'), [postToParent]);

    const getCurrentStep = (): 'details' | 'payment' | 'summary' => {
        const pathname = location.pathname;
        if (pathname.includes('/payment')) return 'payment';
        if (pathname.includes('/summary')) return 'summary';
        return 'details';
    };
    const currentStep = getCurrentStep();

    const isOrderReservedAndNotExpired = orderIsReserved && order?.reserved_until
        && isDateInFuture(order.reserved_until);

    const blocker = useBlocker(
        ({currentLocation, nextLocation}) => {
            const isLeavingCheckout = !nextLocation.pathname.startsWith('/checkout/');
            return (
                !isAbandoning &&
                !!isOrderReservedAndNotExpired &&
                currentLocation.pathname !== nextLocation.pathname &&
                isLeavingCheckout
            );
        }
    );

    const handleExpiry = () => {
        setIsExpired(true);
    };

    const handleReturn = () => {
        if (isModal) {
            closeModal();
            return;
        }
        navigate(`/event/${event?.id}/${event?.slug}`);
    };

    const handleRequestClose = useCallback(() => {
        postToParent('hievents:close-pending');
        if (isOrderReservedAndNotExpired) {
            setPendingClose(true);
            setShowAbandonDialog(true);
        } else {
            closeModal();
        }
    }, [postToParent, closeModal, isOrderReservedAndNotExpired]);

    useEffect(() => {
        if (!isModal) return;
        const parentOrigin = getParentOrigin();
        const onMessage = (event: MessageEvent) => {
            if (parentOrigin && event.origin !== parentOrigin) return;
            if (event.data?.type === 'hievents:request-close') {
                handleRequestClose();
            }
        };
        window.addEventListener('message', onMessage);
        return () => window.removeEventListener('message', onMessage);
    }, [isModal, handleRequestClose]);

    useEffect(() => {
        if (isModal && isOrderError) {
            postToParent('hievents:checkout-cleared');
            closeModal();
        }
    }, [isModal, isOrderError, postToParent, closeModal]);

    useEffect(() => {
        if (!isModal) return;
        if (isOrderReservedAndNotExpired) {
            const parentOrigin = getParentOrigin();
            postToParent('hievents:checkout-progress', {
                eventId,
                orderShortId,
                ...(parentOrigin ? {sessionId: getCheckoutSessionIdentifier(String(orderShortId))} : {}),
                step: currentStep,
                reservedUntil: order?.reserved_until ? utcDateToEpochMs(order.reserved_until) : null,
            });
        } else if (orderIsCompleted || orderIsAwaitingOfflinePayment) {
            postToParent('hievents:checkout-cleared');
        }
    }, [isModal, isOrderReservedAndNotExpired, orderIsCompleted, orderIsAwaitingOfflinePayment, order?.reserved_until, currentStep, eventId, orderShortId, postToParent]);

    useEffect(() => {
        if (!isModal) return;
        const onKeydown = (event: KeyboardEvent) => {
            if (event.key === 'Escape' && !showAbandonDialog && !isExpired) {
                handleRequestClose();
            }
        };
        window.addEventListener('keydown', onKeydown);
        return () => window.removeEventListener('keydown', onKeydown);
    }, [isModal, showAbandonDialog, isExpired, handleRequestClose]);

    useEffect(() => {
        if (isModal && order?.is_expired) {
            setIsExpired(true);
        }
    }, [isModal, order?.is_expired]);

    const handleInvoiceDownload = async (invoice: Invoice) => {
        await withLoadingNotification(
            async () => {
                const blob = await orderClientPublic.downloadInvoice(eventId, orderShortId);
                downloadBinary(blob, invoice.invoice_number + '.pdf');
            },
            {
                loading: {
                    title: t`Downloading Invoice`,
                    message: t`Please wait while we prepare your invoice...`
                },
                success: {
                    title: t`Success`,
                    message: t`Invoice downloaded successfully`
                },
                error: {
                    title: t`Error`,
                    message: t`Failed to download invoice. Please try again.`
                }
            }
        );
    }

    const handleAbandonConfirm = async () => {
        setIsAbandoning(true);
        try {
            await abandonOrderMutation.mutateAsync({
                eventId: Number(eventId),
                orderShortId: String(orderShortId),
            });
        } catch (error) {
            showError(t`Failed to abandon order. Please try again.`);
        } finally {
            setShowAbandonDialog(false);
            showInfo(t`Your order has been cancelled.`);

            if (pendingClose) {
                setPendingClose(false);
                closeModal();
            } else if (blocker.state === 'blocked') {
                blocker.proceed();
            } else if (pendingNavigation) {
                navigate(pendingNavigation);
            }

            setPendingNavigation(null);
            setIsAbandoning(false);
        }
    };

    const handleAbandonCancel = () => {
        if (blocker.state === 'blocked') {
            blocker.reset();
        }
        setShowAbandonDialog(false);
        setPendingNavigation(null);
        setPendingClose(false);
    };

    const handleEventHomepageClick = (e: React.MouseEvent) => {
        if (isOrderReservedAndNotExpired && event) {
            e.preventDefault();
            setPendingNavigation(eventHomepagePath(event));
            setShowAbandonDialog(true);
        } else if (event) {
            navigate(eventHomepagePath(event));
        }
    };

    useEffect(() => {
        if (blocker.state === 'blocked') {
            setShowAbandonDialog(true);
        }
    }, [blocker.state]);

    const {consentPending, consentGranted, onConsent} = useOrganizerTrackingPixels(
        publicEvent?.organizer?.settings?.tracking_pixels
    );

    useEffect(() => {
        if (event && orderIsReserved && consentGranted && hasActivePixels()) {
            trackPixelEvent({
                eventName: 'InitiateCheckout',
                contentName: event.title,
                contentId: event.id,
            });
        }
    }, [event?.id, orderIsReserved, consentGranted]);

    useEffect(() => {
        if (!event || !order || !consentGranted || !hasActivePixels()) return;
        if (!orderIsCompleted && !orderIsAwaitingOfflinePayment) return;

        const key = `purchase_tracked_${order.short_id}`;
        if (typeof sessionStorage !== 'undefined' && sessionStorage.getItem(key)) return;

        trackPixelEvent({
            eventName: 'Purchase',
            value: Number(order.total_gross) || 0,
            currency: order.currency || 'USD',
            contentName: event.title,
            contentId: event.id,
            transactionId: order.short_id,
        });

        if (typeof sessionStorage !== 'undefined') {
            sessionStorage.setItem(key, '1');
        }
    }, [order?.status, order?.short_id, consentGranted]);

    const homepageSettings = event?.settings?.homepage_theme_settings;
    const accentColor = homepageSettings?.accent || DEFAULT_ACCENT;
    const checkoutMode = homepageSettings?.mode || detectMode(homepageSettings?.background || '#ffffff');

    return (
        <CheckoutThemeProvider accentColor={accentColor} mode={checkoutMode}>
            <div className={classes.container} data-mode={checkoutMode}>
                <div className={classes.mainContent}>
                    <header className={classes.header}>
                        {(event) && (
                            <div className={classes.actionBar} style={isModal ? {paddingRight: '44px'} : undefined}>
                                <Group justify="space-between" wrap="nowrap">
                                    {isModal ? (
                                        <span/>
                                    ) : (
                                        <Button
                                            title={t`Back to event page`}
                                            variant="subtle"
                                            leftSection={<IconArrowLeft size={20}/>}
                                            onClick={handleEventHomepageClick}
                                        >
                                            {!isMobile && t`Event Homepage`}
                                        </Button>
                                    )}

                                    {orderIsReserved && (
                                        <ProgressStepper
                                            isPaymentRequired={!!order.is_payment_required}
                                            currentStep={currentStep}
                                        />
                                    )}

                                    {(orderIsCompleted || orderIsAwaitingOfflinePayment) && (
                                        <span className={classes.title}>
                                            {t`Your Order`}
                                        </span>
                                    )}

                                    {orderIsReserved && (
                                        <Group gap="5px" className={classes.timerGroup}>
                                            <span className={classes.timerLabel}>
                                                {t`Time left:`}
                                            </span>
                                            <Countdown
                                                displayType={'short'}
                                                className={classes.countdown}
                                                closeToExpiryClassName={classes.countdownCloseToExpiry}
                                                targetDate={order.reserved_until}
                                                onExpiry={handleExpiry}
                                            />
                                        </Group>
                                    )}

                                    {(orderIsCompleted || orderIsAwaitingOfflinePayment) && (
                                        <Group gap="2px">
                                            <ShareComponent
                                                title={event.title}
                                                text={t`Check out this event!`}
                                                url={eventHomepageUrl(event)}
                                                hideShareButtonText={isMobile}
                                            />

                                            <AddToEventCalendarButton event={event} occurrence={order?.order_items?.[0]?.event_occurrence}/>

                                            {orderHasAttendees && (
                                                <Tooltip label={t`Print Tickets`}>
                                                    <ActionIcon
                                                        variant="subtle"
                                                        onClick={() => window?.open(`/order/${eventId}/${orderShortId}/print`, '_blank')}
                                                    >
                                                        <IconPrinter size={20}/>
                                                    </ActionIcon>
                                                </Tooltip>
                                            )}

                                            {order.latest_invoice && (
                                                <Tooltip
                                                    label={t`Download Invoice`}>
                                                    <ActionIcon
                                                        variant="subtle"
                                                        data-testid="download-invoice-button"
                                                        onClick={() => handleInvoiceDownload(order.latest_invoice as Invoice)}
                                                    >
                                                        <IconReceipt size={20}/>
                                                    </ActionIcon>
                                                </Tooltip>
                                            )}
                                        </Group>
                                    )}
                                </Group>
                            </div>
                        )}
                    </header>
                    <Outlet/>
                    {isModal && currentStep !== 'summary' && (
                        <PoweredByFooter style={{marginTop: '12px', paddingBottom: '16px'}}/>
                    )}
                </div>
            </div>

            <Modal
                opened={isExpired}
                onClose={handleReturn}
                withCloseButton={false}
                centered
                size="m"
            >
                <div style={{textAlign: 'center', padding: '20px 0'}}>
                    <h3 style={{color: 'var(--checkout-text-primary)', margin: '0 0 8px 0'}}>
                        {t`You have run out of time to complete your order.`}
                    </h3>
                    <p style={{color: 'var(--checkout-text-secondary)', margin: '0'}}>
                        {t`Please return to the event page to start over.`}
                    </p>
                    <Button
                        onClick={handleReturn}
                        variant="filled"
                        mt="xl"
                    >
                        {t`Return to Event Page`}
                    </Button>
                </div>
            </Modal>

            <Modal
                opened={showAbandonDialog}
                onClose={handleAbandonCancel}
                withCloseButton={false}
                centered
                size="m"
            >
                <div style={{textAlign: 'center', padding: '20px 0'}}>
                    <h3 style={{color: 'var(--checkout-text-primary)', margin: '0 0 8px 0'}}>
                        {t`Are you sure you want to leave?`}
                    </h3>
                    <p style={{color: 'var(--checkout-text-secondary)', margin: '0'}}>
                        {t`Your current order will be lost.`}
                    </p>
                    <Group justify="center" gap="md" mt="xl">
                        <Button
                            onClick={handleAbandonCancel}
                            variant="subtle"
                        >
                            {t`No, keep me here`}
                        </Button>
                        <Button
                            onClick={handleAbandonConfirm}
                            variant="outline"
                            color="gray"
                            loading={abandonOrderMutation.isPending}
                        >
                            {t`Yes, cancel my order`}
                        </Button>
                    </Group>
                </div>
            </Modal>
            {consentPending && (
                <CookieConsentBanner onConsent={onConsent}/>
            )}
        </CheckoutThemeProvider>
    );
}

export default Checkout;
