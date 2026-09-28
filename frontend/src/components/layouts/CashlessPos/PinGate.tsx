import {t} from "@lingui/macro";
import {Button, PasswordInput} from "@mantine/core";
import {IconLock} from "@tabler/icons-react";
import {useState} from "react";
import {publicCashlessClient} from "../../../api/cashless-public.client.ts";
import {showError} from "../../../utilites/notifications.tsx";
import classes from "./CashlessPos.module.scss";

interface PinGateProps {
    salesPointShortId: string;
    salesPointName?: string;
    onAuthenticated: (token: string) => void;
}

export const PinGate = ({salesPointShortId, salesPointName, onAuthenticated}: PinGateProps) => {
    const [pin, setPin] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: React.FormEvent) => {
        event.preventDefault();
        setIsSubmitting(true);

        try {
            const {token} = await publicCashlessClient.createSession(salesPointShortId, pin);
            onAuthenticated(token);
        } catch (error: any) {
            showError(error?.response?.data?.message || t`That PIN is not correct.`);
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <div className={classes.gate}>
            <form className={classes.gateCard} onSubmit={handleSubmit}>
                <div className={classes.gateIcon}>
                    <IconLock size={26}/>
                </div>
                <h1 className={classes.gateTitle}>{salesPointName ?? t`Sales point`}</h1>
                <p className={classes.gateSubtitle}>{t`Enter the PIN to open this till.`}</p>

                <PasswordInput
                    autoFocus
                    size="xl"
                    inputMode="numeric"
                    autoComplete="off"
                    className={classes.pinInput}
                    value={pin}
                    onChange={(event) => setPin(event.currentTarget.value)}
                    aria-label={t`PIN`}
                    data-testid="cashless-pos-pin-input"
                />

                <Button
                    type="submit"
                    fullWidth
                    size="lg"
                    mt="md"
                    loading={isSubmitting}
                    data-testid="cashless-pos-pin-submit-button"
                >
                    {t`Open till`}
                </Button>
            </form>
        </div>
    );
};
