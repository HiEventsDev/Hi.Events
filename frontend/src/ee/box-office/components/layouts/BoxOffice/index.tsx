import {useEffect, useState} from "react";
import {useParams} from "react-router";
import {ActionIcon, Button, Loader, Modal} from "@mantine/core";
import {useDisclosure, useNetwork} from "@mantine/hooks";
import {t, Trans} from "@lingui/macro";
import {IconCalendarEvent, IconChevronDown, IconDeviceMobile, IconInfoCircle, IconQrcode, IconReceipt, IconShoppingCart, IconWifiOff} from "@tabler/icons-react";
import {useGetBoxOfficePublic} from "../../../queries/useGetBoxOfficePublic.ts";
import {useBoxOfficeSession} from "../../../hooks/useBoxOfficeSession.ts";
import {BOX_OFFICE_UNAVAILABLE_EVENT} from "../../../utilites/boxOfficeSession.ts";
import {isHiEvents, isSsr} from "../../../../../utilites/helpers.ts";
import {PoweredByFooter} from "../../../../../components/common/PoweredByFooter";
import {useUpdateBoxOfficeSession} from "../../../mutations/useUpdateBoxOfficeSession.ts";
import {useEndBoxOfficeSession} from "../../../mutations/useEndBoxOfficeSession.ts";
import {useHashTab} from "../../../../../hooks/useHashTab.ts";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import {NoResultsSplash} from "../../../../../components/common/NoResultsSplash";
import {Countdown} from "../../../../../components/common/Countdown";
import Truncate from "../../../../../components/common/Truncate";
import {BottomNav, BottomNavTab} from "../../../../../components/layouts/CheckIn/BottomNav.tsx";
import shell from "../../../../../components/layouts/CheckIn/CheckIn.module.scss";
import {StartSession} from "./StartSession.tsx";
import {OccurrenceSwitchSheet} from "./OccurrenceSwitchSheet.tsx";
import {ReaderSwitchSheet} from "./ReaderSwitchSheet.tsx";
import {SellTab} from "./tabs/SellTab.tsx";
import {OrdersTab} from "./tabs/OrdersTab.tsx";
import {ScanTab} from "./tabs/ScanTab.tsx";
import classes from "./BoxOffice.module.scss";

type BoxOfficeTab = 'sell' | 'orders' | 'scan';

const BOX_OFFICE_TABS: readonly BoxOfficeTab[] = ['sell', 'orders', 'scan'];

const BoxOffice = () => {
    const {boxOfficeShortId} = useParams();
    const networkStatus = useNetwork();
    const boxOfficeQuery = useGetBoxOfficePublic(boxOfficeShortId);
    const boxOffice = boxOfficeQuery.data?.data;
    const session = useBoxOfficeSession(boxOfficeShortId);
    const [activeTab, setActiveTab] = useHashTab(BOX_OFFICE_TABS, 'sell');
    const [infoOpen, infoHandlers] = useDisclosure(false);
    const [switchOpen, switchHandlers] = useDisclosure(false);
    const [readerOpen, readerHandlers] = useDisclosure(false);
    const [saleInProgress, setSaleInProgress] = useState(false);
    const updateSession = useUpdateBoxOfficeSession();
    const endSession = useEndBoxOfficeSession();

    useEffect(() => {
        if (isSsr()) return;
        const handleUnavailable = () => boxOfficeQuery.refetch();
        window.addEventListener(BOX_OFFICE_UNAVAILABLE_EVENT, handleUnavailable);
        return () => window.removeEventListener(BOX_OFFICE_UNAVAILABLE_EVENT, handleUnavailable);
    }, [boxOfficeQuery.refetch]);

    const activeSession = session.status === 'active' ? session.session : null;
    const staleReader = activeSession?.reader && boxOffice && !boxOffice.readers.some(r => r.id === activeSession.reader?.id);
    useEffect(() => {
        if (!staleReader || !boxOfficeShortId || !activeSession || updateSession.isPending) return;
        updateSession.mutate({boxOfficeShortId, payload: {stripe_terminal_reader_id: null}}, {
            onSuccess: ({data}) => session.update({...activeSession, ...data, reader: undefined}),
        });
    }, [staleReader]);

    const tabs: BottomNavTab<BoxOfficeTab>[] = [
        {id: 'sell', label: t`Sell`, icon: <IconShoppingCart size={20} stroke={1.8}/>},
        {id: 'orders', label: t`Orders`, icon: <IconReceipt size={20} stroke={1.8}/>},
        {id: 'scan', label: t`Scan`, icon: <IconQrcode size={22} stroke={1.7}/>},
    ];

    if (boxOfficeQuery.error && (boxOfficeQuery.error as any).response?.status === 404) {
        return (
            <NoResultsSplash
                heading={t`Box office not found`}
                imageHref={"/blank-slate/check-in-lists.svg"}
                subHeading={<p>{t`This box office no longer exists. Ask the organizer for a new link.`}</p>}
            />
        );
    }

    if (boxOfficeQuery.isError && !boxOffice) {
        return (
            <NoResultsSplash
                heading={t`We couldn't load this box office`}
                imageHref={"/blank-slate/check-in-lists.svg"}
                subHeading={<p>{t`Check your connection and try again.`}</p>}
            >
                <Button loading={boxOfficeQuery.isFetching} onClick={() => boxOfficeQuery.refetch()}
                        data-testid="box-office-retry-button">
                    {t`Try again`}
                </Button>
            </NoResultsSplash>
        );
    }

    if (!boxOffice || !boxOfficeShortId || session.status === 'loading') {
        return <div className={classes.splash}><Loader/></div>;
    }

    if (boxOffice.is_expired) {
        return (
            <NoResultsSplash
                heading={t`Box office has closed`}
                imageHref={"/blank-slate/check-in-lists.svg"}
                subHeading={<p>{t`This box office has expired and is no longer taking sales.`}</p>}
            />
        );
    }

    if (!boxOffice.is_active) {
        return (
            <NoResultsSplash
                heading={t`Box office is not open yet`}
                imageHref={"/blank-slate/check-in-lists.svg"}
                subHeading={(
                    <>
                        <p>{t`Sales open in`}</p>
                        <p>
                            <b>
                                <Countdown
                                    targetDate={boxOffice.activates_at as string}
                                    onExpiry={() => boxOfficeQuery.refetch()}
                                />
                            </b>
                        </p>
                    </>
                )}
            />
        );
    }

    const currentSession = session.status === 'active' ? session.session : session.previous;

    if (!currentSession) {
        return (
            <StartSession
                boxOffice={boxOffice}
                boxOfficeShortId={boxOfficeShortId}
                sessionExpired={session.status === 'none' && session.expired}
                onStarted={session.start}
            />
        );
    }

    const saleLockedTitle = saleInProgress ? t`Finish or cancel the current sale first` : undefined;
    const occurrence = currentSession.event_occurrence;
    const eventNotLive = boxOffice.event.status !== 'LIVE';
    const canSwitchOccurrence = boxOffice.occurrences.length > 1;
    const occurrenceLabel = occurrence && (
        formatDateWithLocale(occurrence.start_date, 'shortDate', boxOffice.event.timezone)
        + ' · ' + formatDateWithLocale(occurrence.start_date, 'timeOnly', boxOffice.event.timezone)
        + (occurrence.label ? ` · ${occurrence.label}` : '')
    );

    return (
        <div className={shell.app}>
            <header className={shell.topBar}>
                <div className={shell.topBarMain}>
                    <div className={shell.topLabel}>{t`Box office`}</div>
                    <div className={shell.topTitle}>
                        <Truncate text={boxOffice.name} length={26}/>
                    </div>
                    {occurrence && canSwitchOccurrence && (
                        <button
                            type="button"
                            className={`${shell.topScope} ${classes.scopeButton}`}
                            onClick={switchHandlers.open}
                            disabled={saleInProgress}
                            title={saleInProgress ? t`Finish or cancel the current sale to change date` : t`Change date`}
                            data-testid="box-office-occurrence-switch"
                        >
                            <IconCalendarEvent size={12}/>
                            <span>{occurrenceLabel}</span>
                            <IconChevronDown size={12}/>
                        </button>
                    )}
                    {occurrence && !canSwitchOccurrence && (
                        <div className={shell.topScope}>
                            <IconCalendarEvent size={12}/>
                            <span>{occurrenceLabel}</span>
                        </div>
                    )}
                </div>
                <div className={shell.topRight}>
                    {eventNotLive && (
                        <div className={`${classes.chip} ${classes.chipWarn}`}>{t`Event not live`}</div>
                    )}
                    {currentSession.reader && (
                        <button type="button" className={`${classes.chip} ${classes.chipButton}`} aria-label={t`Card reader`}
                                onClick={readerHandlers.open} disabled={saleInProgress} title={saleLockedTitle}
                                data-testid="box-office-reader-chip">
                            <IconDeviceMobile size={13}/>
                            {currentSession.reader.label}
                        </button>
                    )}
                    <div className={classes.chip} aria-label={t`Signed in as`}>{currentSession.operator_name}</div>
                    {!networkStatus.online && (
                        <div className={shell.offlineBadge} aria-label={t`Offline`}>
                            <IconWifiOff size={14}/>
                            <span>{t`Offline`}</span>
                        </div>
                    )}
                    <ActionIcon
                        variant="subtle"
                        color="gray"
                        radius="xl"
                        onClick={infoHandlers.open}
                        aria-label={t`Box office info`}
                        className={shell.infoBtn}
                    >
                        <IconInfoCircle size={20}/>
                    </ActionIcon>
                </div>
            </header>

            <main className={shell.content}>
                <div className={classes.tabHost} hidden={activeTab !== 'sell'}>
                    <SellTab boxOffice={boxOffice} boxOfficeShortId={boxOfficeShortId} session={currentSession}
                             onSaleStateChange={setSaleInProgress} onSessionUpdated={session.update}/>
                </div>
                {activeTab === 'orders' && (
                    <OrdersTab boxOffice={boxOffice} boxOfficeShortId={boxOfficeShortId} session={currentSession}/>
                )}
                {activeTab === 'scan' && (
                    <ScanTab boxOffice={boxOffice} session={currentSession}/>
                )}
            </main>

            <BottomNav tabs={tabs} active={activeTab} onChange={setActiveTab} ariaLabel={t`Box office navigation`}/>

            {canSwitchOccurrence && switchOpen && (
                <OccurrenceSwitchSheet
                    opened
                    onClose={switchHandlers.close}
                    boxOffice={boxOffice}
                    boxOfficeShortId={boxOfficeShortId}
                    session={currentSession}
                    onUpdated={session.update}
                />
            )}

            {readerOpen && (
                <ReaderSwitchSheet
                    opened
                    onClose={readerHandlers.close}
                    boxOfficeShortId={boxOfficeShortId}
                    session={currentSession}
                    onUpdated={session.update}
                />
            )}

            <Modal opened={infoOpen} onClose={infoHandlers.close} title={boxOffice.name} centered>
                <div className={classes.infoRow}>
                    <span className={classes.infoLabel}>{t`Event`}</span>
                    <span className={classes.infoValue}>{boxOffice.event.title}</span>
                </div>
                <div className={classes.infoRow}>
                    <span className={classes.infoLabel}>{t`Selling as`}</span>
                    <span className={classes.infoValue}>{currentSession.operator_name}</span>
                </div>
                <div className={classes.infoRow}>
                    <span className={classes.infoLabel}>{t`Card reader`}</span>
                    <span className={classes.infoValue}>
                        {currentSession.reader?.label ?? t`None (cash only)`}
                        {boxOffice.card_payments_enabled && (
                            <Button variant="subtle" size="compact-xs" ml="xs" disabled={saleInProgress} title={saleLockedTitle} onClick={() => {
                                infoHandlers.close();
                                readerHandlers.open();
                            }} data-testid="box-office-change-reader-button">
                                {t`Change`}
                            </Button>
                        )}
                    </span>
                </div>
                {occurrence && (
                    <div className={classes.infoRow}>
                        <span className={classes.infoLabel}>{t`Date`}</span>
                        <span className={classes.infoValue}>
                            {formatDateWithLocale(occurrence.start_date, 'shortDate', boxOffice.event.timezone)}
                        </span>
                    </div>
                )}
                {eventNotLive && (
                    <p style={{fontSize: 13, color: 'var(--hi-color-gray-dark)'}}>
                        <Trans>This event is not published. Sales are still recorded.</Trans>
                    </p>
                )}
                <Button mt="md" fullWidth variant="light" color="gray" disabled={saleInProgress} title={saleLockedTitle} onClick={() => {
                    infoHandlers.close();
                    endSession.mutate({boxOfficeShortId, token: currentSession.token});
                    session.clear();
                }} data-testid="box-office-sign-out-button">
                    {t`Sign out`}
                </Button>
                {!isHiEvents() && <PoweredByFooter style={{marginTop: '16px'}}/>}
            </Modal>

            <Modal
                opened={session.status === 'none'}
                onClose={() => undefined}
                withCloseButton={false}
                closeOnClickOutside={false}
                closeOnEscape={false}
                title={t`Sign in again to keep selling`}
                centered
            >
                <StartSession
                    boxOffice={boxOffice}
                    boxOfficeShortId={boxOfficeShortId}
                    sessionExpired
                    previousSession={currentSession}
                    onStarted={session.start}
                />
            </Modal>
        </div>
    );
};

export default BoxOffice;
