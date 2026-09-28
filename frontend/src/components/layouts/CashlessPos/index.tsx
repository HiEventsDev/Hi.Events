import {t} from "@lingui/macro";
import {IconArrowsExchange, IconCoin, IconLock, IconReceipt} from "@tabler/icons-react";
import {useCallback, useEffect, useRef, useState} from "react";
import {useParams} from "react-router";
import {useQueryClient} from "@tanstack/react-query";
import {CashlessStaffPaymentMethod, CashlessWalletPublic, Product} from "../../../types.ts";
import {publicCashlessClient} from "../../../api/cashless-public.client.ts";
import {useGetCashlessSalesPointPublic} from "../../../queries/useGetCashlessSalesPointPublic.ts";
import {
    GET_CASHLESS_SALES_POINT_TRANSACTIONS_QUERY_KEY,
    useGetCashlessSalesPointTransactions,
} from "../../../queries/useGetCashlessSalesPointTransactions.ts";
import {HomepageInfoMessage} from "../../common/HomepageInfoMessage";
import {FloatingTabBar} from "../../common/FloatingTabBar";
import {ScanMode} from "../../common/TicketScanZone";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {formatCurrency} from "../../../utilites/currency.ts";
import {isSsr} from "../../../utilites/helpers.ts";
import {useUsbBarcodeScanner} from "../../../hooks/useUsbBarcodeScanner.ts";
import {usePosSession} from "./usePosSession.ts";
import {useCashlessQuote} from "./useCashlessQuote.ts";
import {PinGate} from "./PinGate.tsx";
import {CartLine, ChargeTab} from "./ChargeTab.tsx";
import {TopUpTab} from "./TopUpTab.tsx";
import {HistoryTab} from "./HistoryTab.tsx";
import classes from "./CashlessPos.module.scss";

type PosTab = 'charge' | 'topup' | 'history';

const newClientReference = () =>
    (typeof crypto !== 'undefined' && 'randomUUID' in crypto)
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2)}`;

const CashlessPos = () => {
    const {salesPointShortId} = useParams();
    const {token, storeToken, clearToken} = usePosSession(String(salesPointShortId));
    const {data: salesPoint, isError} = useGetCashlessSalesPointPublic(salesPointShortId, token);

    const queryClient = useQueryClient();
    const [wallet, setWallet] = useState<CashlessWalletPublic | null>(null);
    const [scannedId, setScannedId] = useState('');
    const [cart, setCart] = useState<CartLine[]>([]);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [reversingShortId, setReversingShortId] = useState<string | null>(null);
    const [scannerResetToken, setScannerResetToken] = useState(0);
    const [topupAmount, setTopupAmount] = useState(20);
    const [activeTab, setActiveTab] = useState<PosTab | null>(null);
    const [scanMode, setScanMode] = useState<ScanMode>(() => {
        if (isSsr()) return "camera";
        return localStorage.getItem("cashlessPosScanMode") === "usb" ? "usb" : "camera";
    });
    const [isSoundOn, setIsSoundOn] = useState(() => {
        if (isSsr()) return true;
        const storedIsSoundOn = localStorage.getItem("cashlessPosSoundOn");
        return storedIsSoundOn === null ? true : JSON.parse(storedIsSoundOn);
    });
    const scanSuccessAudioRef = useRef<HTMLAudioElement | null>(null);
    const scanErrorAudioRef = useRef<HTMLAudioElement | null>(null);

    useEffect(() => {
        if (!isSsr()) {
            localStorage.setItem("cashlessPosScanMode", scanMode);
        }
    }, [scanMode]);

    useEffect(() => {
        if (!isSsr()) {
            localStorage.setItem("cashlessPosSoundOn", JSON.stringify(isSoundOn));
        }
    }, [isSoundOn]);

    const playSuccessSound = useCallback(() => {
        if (isSoundOn && scanSuccessAudioRef.current) {
            scanSuccessAudioRef.current.currentTime = 0;
            scanSuccessAudioRef.current.play().catch(() => {
            });
        }
    }, [isSoundOn]);

    const playErrorSound = useCallback(() => {
        if (isSoundOn && scanErrorAudioRef.current) {
            scanErrorAudioRef.current.currentTime = 0;
            scanErrorAudioRef.current.play().catch(() => {
            });
        }
    }, [isSoundOn]);

    const basket = cart.map((line) => ({
        product_id: line.product_id,
        product_price_id: line.product_price_id,
        quantity: line.quantity,
    }));
    const chargeQuote = useCashlessQuote(String(salesPointShortId), token, {items: basket});
    const topupQuote = useCashlessQuote(String(salesPointShortId), token, {topupAmount});

    const isUnlocked = !!salesPoint && (!salesPoint.requires_pin || !!salesPoint.products);
    const {data: transactions = []} = useGetCashlessSalesPointTransactions(salesPointShortId, token, isUnlocked);
    const refreshTransactions = () => queryClient.invalidateQueries({
        queryKey: [GET_CASHLESS_SALES_POINT_TRANSACTIONS_QUERY_KEY, salesPointShortId],
    });

    const currency = salesPoint?.currency ?? 'USD';
    const sellsProducts = (salesPoint?.products?.length ?? 0) > 0;
    const currentTab: PosTab = activeTab ?? (sellsProducts ? 'charge' : 'topup');

    const lookUpWallet = async (attendeePublicId: string) => {
        setScannedId(attendeePublicId);

        try {
            const {data} = await publicCashlessClient.getWalletAtSalesPoint(
                String(salesPointShortId), attendeePublicId, token,
            );
            setWallet(data);
            playSuccessSound();
        } catch (error: any) {
            showError(error?.response?.data?.message || t`That ticket could not be found.`);
            playErrorSound();
        } finally {
            setScannedId('');
            setScannerResetToken((value) => value + 1);
        }
    };

    const resetCustomer = () => {
        setWallet(null);
        setCart([]);
        setScannedId('');
        setScannerResetToken((value) => value + 1);
    };

    const processScannedCode = (code: string) => {
        if (code.startsWith("A-") && code.length > 3) {
            lookUpWallet(code);
        }
    };

    const usbScannerEnabled = !wallet && scanMode === "usb" && (currentTab === "charge" || currentTab === "topup");
    const {hidBuffer, pageHasFocus} = useUsbBarcodeScanner(usbScannerEnabled, processScannedCode);

    const addLine = (product: Product, priceId: number, title: string, unitPrice: number) => {
        setCart((lines) => {
            const existing = lines.find((line) => line.product_price_id === priceId);

            if (existing) {
                return lines.map((line) => line.product_price_id === priceId
                    ? {...line, quantity: line.quantity + 1}
                    : line);
            }

            return [...lines, {
                product_id: Number(product.id),
                product_price_id: priceId,
                title,
                unitPrice,
                quantity: 1,
            }];
        });
    };

    const changeQuantity = (priceId: number, delta: number) => {
        setCart((lines) => lines
            .map((line) => line.product_price_id === priceId
                ? {...line, quantity: line.quantity + delta}
                : line)
            .filter((line) => line.quantity > 0));
    };

    const charge = async () => {
        if (!wallet?.attendee_public_id) {
            return;
        }

        setIsSubmitting(true);

        try {
            const {data} = await publicCashlessClient.createPurchase(String(salesPointShortId), {
                attendee_public_id: wallet.attendee_public_id,
                client_reference_id: newClientReference(),
                items: cart.map((line) => ({
                    product_id: line.product_id,
                    product_price_id: line.product_price_id,
                    quantity: line.quantity,
                })),
            }, token);

            showSuccess(t`Charged ${formatCurrency(Math.abs(data.amount), currency)} — ${formatCurrency(data.balance_after, currency)} left`);
            playSuccessSound();
            refreshTransactions();
            resetCustomer();
        } catch (error: any) {
            showError(error?.response?.data?.message || t`This payment could not be taken.`);
            playErrorSound();
        } finally {
            setIsSubmitting(false);
        }
    };

    const topUp = async (amount: number, paymentMethod: CashlessStaffPaymentMethod) => {
        if (!wallet?.attendee_public_id) {
            return;
        }

        setIsSubmitting(true);

        try {
            const {data} = await publicCashlessClient.createStaffTopup(String(salesPointShortId), {
                attendee_public_id: wallet.attendee_public_id,
                client_reference_id: newClientReference(),
                amount,
                payment_method: paymentMethod,
            }, token);

            showSuccess(t`Added ${formatCurrency(amount, currency)} — balance is now ${formatCurrency(data.balance_after, currency)}`);
            playSuccessSound();
            refreshTransactions();
            resetCustomer();
        } catch (error: any) {
            showError(error?.response?.data?.message || t`This top-up could not be recorded.`);
            playErrorSound();
        } finally {
            setIsSubmitting(false);
        }
    };

    const reverse = async (transactionShortId: string) => {
        setReversingShortId(transactionShortId);

        try {
            await publicCashlessClient.reverseTransaction(String(salesPointShortId), transactionShortId, token);
            showSuccess(t`Transaction undone`);
            refreshTransactions();
        } catch (error: any) {
            showError(error?.response?.data?.message || t`This transaction could not be undone.`);
        } finally {
            setReversingShortId(null);
        }
    };

    if (isError) {
        return (
            <HomepageInfoMessage
                status="not_found"
                message={t`Sales point not found`}
                subtitle={t`This link is no longer valid. Ask the organizer for a new one.`}
            />
        );
    }

    if (!salesPoint) {
        return null;
    }

    if (salesPoint.requires_pin && !salesPoint.products) {
        return (
            <PinGate
                salesPointShortId={String(salesPointShortId)}
                salesPointName={salesPoint.name}
                onAuthenticated={storeToken}
            />
        );
    }

    return (
        <div className={classes.pos}>
            <header className={classes.header}>
                <div className={classes.headerMain}>
                    <div className={classes.topLabel}>{t`Sales point`}</div>
                    <div className={classes.topTitle}>{salesPoint.name}</div>
                    {salesPoint.event_title && (
                        <div className={classes.topScope}>{salesPoint.event_title}</div>
                    )}
                </div>
                {salesPoint.requires_pin && (
                    <button
                        type="button"
                        className={classes.lockButton}
                        onClick={clearToken}
                        data-testid="cashless-pos-lock-button"
                    >
                        <IconLock size={14}/>
                        {t`Lock till`}
                    </button>
                )}
            </header>

            <main className={classes.content}>
                {currentTab === 'charge' && sellsProducts && (
                    <div className={classes.panel}>
                        <ChargeTab
                            salesPoint={salesPoint}
                            wallet={wallet}
                            scannedId={scannedId}
                            cart={cart}
                            isCharging={isSubmitting}
                            onScan={lookUpWallet}
                            onManualLookup={lookUpWallet}
                            onAddLine={addLine}
                            onChangeQuantity={changeQuantity}
                            onClear={resetCustomer}
                            onCharge={charge}
                            scannerResetToken={scannerResetToken}
                            quote={chargeQuote}
                            scanMode={scanMode}
                            onScanModeChange={setScanMode}
                            hidBuffer={hidBuffer}
                            hidPageHasFocus={pageHasFocus}
                            isSoundOn={isSoundOn}
                            onSoundToggle={() => setIsSoundOn(!isSoundOn)}
                        />
                    </div>
                )}

                {currentTab === 'topup' && (
                    <div className={classes.panel}>
                        <TopUpTab
                            salesPoint={salesPoint}
                            wallet={wallet}
                            isSubmitting={isSubmitting}
                            onScan={lookUpWallet}
                            onManualLookup={lookUpWallet}
                            onClear={resetCustomer}
                            onTopUp={topUp}
                            scannerResetToken={scannerResetToken}
                            quote={topupQuote}
                            onAmountChange={setTopupAmount}
                            scanMode={scanMode}
                            onScanModeChange={setScanMode}
                            hidBuffer={hidBuffer}
                            hidPageHasFocus={pageHasFocus}
                            isSoundOn={isSoundOn}
                            onSoundToggle={() => setIsSoundOn(!isSoundOn)}
                        />
                    </div>
                )}

                {currentTab === 'history' && (
                    <div className={classes.panel}>
                        <HistoryTab
                            transactions={transactions}
                            currency={currency}
                            reversingShortId={reversingShortId}
                            onReverse={reverse}
                        />
                    </div>
                )}
            </main>

            <FloatingTabBar
                ariaLabel={t`Sales point navigation`}
                active={currentTab}
                onChange={setActiveTab}
                items={[
                    ...(sellsProducts ? [{id: 'charge' as PosTab, label: t`Charge`, icon: <IconReceipt size={20} stroke={1.8}/>}] : []),
                    {id: 'topup' as PosTab, label: t`Top up`, icon: <IconCoin size={20} stroke={1.8}/>},
                    {id: 'history' as PosTab, label: t`History`, icon: <IconArrowsExchange size={20} stroke={1.8}/>},
                ]}
            />

            <audio ref={scanSuccessAudioRef} src="/sounds/scan-success.wav"/>
            <audio ref={scanErrorAudioRef} src="/sounds/scan-error.wav"/>
        </div>
    );
};

export default CashlessPos;
