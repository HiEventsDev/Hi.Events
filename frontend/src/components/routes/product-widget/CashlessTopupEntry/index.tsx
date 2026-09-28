import {t} from "@lingui/macro";
import {Button, Container, TextInput} from "@mantine/core";
import {IconQrcode, IconWallet} from "@tabler/icons-react";
import {useState} from "react";
import {useNavigate, useParams} from "react-router";
import {useGetEventPublic} from "../../../../queries/useGetEventPublic.ts";
import {HomepageInfoMessage} from "../../../common/HomepageInfoMessage";
import {InlineCameraScanner} from "../../../common/InlineCameraScanner";
import {PoweredByFooter} from "../../../common/PoweredByFooter";
import {showError} from "../../../../utilites/notifications.tsx";
import classes from './CashlessTopupEntry.module.scss';

const TICKET_ID_PATTERN = /^A-[A-Z0-9]{7}$/;

const CashlessTopupEntry = () => {
    const {eventId} = useParams();
    const navigate = useNavigate();
    const {data: event, isError} = useGetEventPublic(eventId);
    const [ticketId, setTicketId] = useState('');
    const [isScanning, setIsScanning] = useState(false);

    const goToBalance = (rawTicketId: string) => {
        const normalised = rawTicketId.trim().toUpperCase();

        if (!TICKET_ID_PATTERN.test(normalised)) {
            showError(t`That doesn't look like a ticket ID. It starts with A- and is printed under the QR code.`);
            return;
        }

        navigate(`/cashless/${eventId}/${normalised}`);
    };

    if (isError) {
        return (
            <HomepageInfoMessage
                status="not_found"
                message={t`Event not found`}
                subtitle={t`We couldn't find this event.`}
            />
        );
    }

    if (!event) {
        return null;
    }

    if (!event.settings?.cashless_enabled || !event.settings?.cashless_online_topup_enabled) {
        return (
            <HomepageInfoMessage
                status="not_found"
                message={t`Online top-ups are not available`}
                subtitle={t`Top up your balance at any top-up point during the event.`}
            />
        );
    }

    return (
        <Container className={classes.container}>
            <h2 className={classes.title}>{t`Top up my balance`}</h2>
            <p className={classes.subtitle}>{event.title}</p>

            <div className={classes.card}>
                {isScanning ? (
                    <div className={classes.scannerFrame}>
                        <InlineCameraScanner onAttendeeScanned={goToBalance}/>
                    </div>
                ) : (
                    <Button
                        fullWidth
                        size="md"
                        variant="light"
                        leftSection={<IconQrcode size={18}/>}
                        onClick={() => setIsScanning(true)}
                        data-testid="cashless-entry-scan-button"
                    >
                        {t`Scan my ticket QR code`}
                    </Button>
                )}

                <form
                    className={classes.manual}
                    onSubmit={(formEvent) => {
                        formEvent.preventDefault();
                        goToBalance(ticketId);
                    }}
                >
                    <TextInput
                        size="md"
                        label={t`Or type your ticket ID`}
                        description={t`Printed under the QR code on your ticket, like A-XXXXXXX.`}
                        placeholder="A-XXXXXXX"
                        autoCapitalize="characters"
                        autoComplete="off"
                        value={ticketId}
                        onChange={(inputEvent) => setTicketId(inputEvent.currentTarget.value)}
                        data-testid="cashless-entry-ticket-input"
                    />

                    <Button
                        type="submit"
                        fullWidth
                        size="md"
                        mt="md"
                        leftSection={<IconWallet size={18}/>}
                        disabled={!ticketId.trim()}
                        data-testid="cashless-entry-continue-button"
                    >
                        {t`Continue`}
                    </Button>
                </form>
            </div>

            <PoweredByFooter/>
        </Container>
    );
};

export default CashlessTopupEntry;
