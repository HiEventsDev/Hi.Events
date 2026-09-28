import {t} from "@lingui/macro";
import {Alert, Button, Radio, Stack, Text} from "@mantine/core";
import {IconAlertCircle} from "@tabler/icons-react";
import {useState} from "react";
import {useParams} from "react-router";
import {Modal} from "../../common/Modal";
import {CashlessWallet} from "../../../types.ts";
import {useRefundCashlessWallet} from "../../../mutations/useRefundCashlessWallet.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {formatCurrency} from "../../../utilites/currency.ts";

interface CashlessRefundModalProps {
    wallet: CashlessWallet;
    onClose: () => void;
}

export const CashlessRefundModal = ({wallet, onClose}: CashlessRefundModalProps) => {
    const {eventId} = useParams();
    const [method, setMethod] = useState<'ORIGINAL_PAYMENT' | 'CASH'>('ORIGINAL_PAYMENT');
    const refundMutation = useRefundCashlessWallet();

    const handleRefund = () => {
        refundMutation.mutate({eventId, walletId: wallet.id, method}, {
            onSuccess: ({data}) => {
                if (data.unrefundable_amount > 0) {
                    showSuccess(t`Refunded ${formatCurrency(data.refunded_amount, wallet.currency)}. ${formatCurrency(data.unrefundable_amount, wallet.currency)} was loaded as cash and must be handed back in person.`);
                } else {
                    showSuccess(t`Refunded ${formatCurrency(data.refunded_amount, wallet.currency)}`);
                }
                onClose();
            },
            onError: (error: any) => showError(
                error?.response?.data?.message || t`This balance could not be refunded`
            ),
        });
    };

    return (
        <Modal opened onClose={onClose} heading={t`Refund remaining balance`}>
            <Stack>
                <Text>
                    {t`Remaining balance`}: <strong>{formatCurrency(wallet.balance, wallet.currency)}</strong>
                </Text>

                <Radio.Group
                    value={method}
                    onChange={(value) => setMethod(value as 'ORIGINAL_PAYMENT' | 'CASH')}
                    label={t`How should this be refunded?`}
                >
                    <Stack gap="xs" mt="xs">
                        <Radio
                            value="ORIGINAL_PAYMENT"
                            label={t`Back to the original payment method`}
                            description={t`Money is sent back to the cards used for online top-ups, newest first.`}
                        />
                        <Radio
                            value="CASH"
                            label={t`Handed back in person`}
                            description={t`Records the refund without moving any money. Use this when you hand cash back at the venue.`}
                        />
                    </Stack>
                </Radio.Group>

                {method === 'ORIGINAL_PAYMENT' && (
                    <Alert icon={<IconAlertCircle size={16}/>} color="yellow">
                        {t`Any balance that was loaded as cash at a sales point has no card to return to. We will tell you how much is left to hand back.`}
                    </Alert>
                )}

                <Button
                    color="red"
                    loading={refundMutation.isPending}
                    onClick={handleRefund}
                    data-testid="cashless-refund-submit-button"
                >
                    {t`Refund balance`}
                </Button>
            </Stack>
        </Modal>
    );
};
