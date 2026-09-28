import {t} from "@lingui/macro";
import {Alert, Button, Checkbox, Stack, Text} from "@mantine/core";
import {IconAlertCircle} from "@tabler/icons-react";
import {useState} from "react";
import {useParams} from "react-router";
import {Modal} from "../../common/Modal";
import {CashlessSettings, CashlessSummary} from "../../../types.ts";
import {useCloseCashless} from "../../../mutations/useCloseCashless.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {formatCurrency} from "../../../utilites/currency.ts";

interface CashlessCloseModalProps {
    settings: CashlessSettings;
    summary: CashlessSummary;
    currency: string;
    onClose: () => void;
}

const isRefundWindowOpen = (settings: CashlessSettings): boolean => {
    if (!settings.cashless_allow_remaining_balance_refund) {
        return false;
    }

    return !settings.cashless_refund_deadline_at || new Date(settings.cashless_refund_deadline_at) > new Date();
};

export const CashlessCloseModal = ({settings, summary, currency, onClose}: CashlessCloseModalProps) => {
    const {eventId} = useParams();
    const [confirmed, setConfirmed] = useState(false);
    const closeMutation = useCloseCashless();
    const refundsStillOpen = isRefundWindowOpen(settings);

    const handleClose = () => {
        closeMutation.mutate({eventId}, {
            onSuccess: ({data}) => {
                showSuccess(t`Cashless closed. ${formatCurrency(data.amount_closed, currency)} moved to your sales.`);
                onClose();
            },
            onError: (error: any) => showError(
                error?.response?.data?.message || t`Cashless could not be closed. Please try again.`
            ),
        });
    };

    return (
        <Modal opened onClose={onClose} heading={t`Close cashless`}>
            <Stack>
                <Text>
                    {t`Closing moves the money left in balances into your total sales and locks every balance. Nobody can top up, spend or be refunded afterwards, and this cannot be undone.`}
                </Text>

                <Text>
                    {t`Moving to sales`}: <strong data-testid="cashless-close-amount">
                        {formatCurrency(summary.outstanding_balance, currency)}
                    </strong> {t`across ${summary.wallets_with_balance} balances`}
                </Text>

                {refundsStillOpen && (
                    <Alert icon={<IconAlertCircle size={16}/>} color="yellow" data-testid="cashless-close-refund-warning">
                        {t`Balance refunds are still open. Turn them off or wait for the refund deadline in the cashless settings before closing.`}
                    </Alert>
                )}

                <Checkbox
                    checked={confirmed}
                    onChange={(event) => setConfirmed(event.currentTarget.checked)}
                    label={t`I understand this cannot be undone`}
                    data-testid="cashless-close-confirm-checkbox"
                />

                <Button
                    color="red"
                    loading={closeMutation.isPending}
                    disabled={!confirmed || refundsStillOpen}
                    onClick={handleClose}
                    data-testid="cashless-close-submit-button"
                >
                    {t`Close cashless`}
                </Button>
            </Stack>
        </Modal>
    );
};
