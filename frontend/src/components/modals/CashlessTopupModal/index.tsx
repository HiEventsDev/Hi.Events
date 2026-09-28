import {t} from "@lingui/macro";
import {Button, NumberInput, Select, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {Modal} from "../../common/Modal";
import {CashlessStaffPaymentMethod, CashlessWallet} from "../../../types.ts";
import {useCreateCashlessWalletTopup} from "../../../mutations/useCreateCashlessWalletTopup.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {formatCurrency} from "../../../utilites/currency.ts";

interface CashlessTopupModalProps {
    wallet: CashlessWallet;
    onClose: () => void;
}

interface TopupFormValues {
    amount: number | string;
    payment_method: CashlessStaffPaymentMethod;
    notes: string;
}

export const CashlessTopupModal = ({wallet, onClose}: CashlessTopupModalProps) => {
    const {eventId} = useParams();
    const topupMutation = useCreateCashlessWalletTopup();
    const errorHandler = useFormErrorResponseHandler();

    const form = useForm<TopupFormValues>({
        initialValues: {
            amount: 10,
            payment_method: 'CASH',
            notes: '',
        },
        validate: {
            amount: (value) => Number(value) > 0 ? null : t`Enter an amount greater than zero`,
        },
    });

    const handleSubmit = form.onSubmit((values) => {
        topupMutation.mutate({
            eventId,
            walletId: wallet.id,
            topupData: {
                amount: Number(values.amount),
                payment_method: values.payment_method,
                notes: values.notes || undefined,
            },
        }, {
            onSuccess: () => {
                showSuccess(t`Balance topped up`);
                onClose();
            },
            onError: (error: any) => errorHandler(form, error, t`Failed to top up this balance`),
        });
    });

    return (
        <Modal
            opened
            onClose={onClose}
            heading={t`Top up cashless balance`}
        >
            <form onSubmit={handleSubmit}>
                <TextInput
                    label={t`Attendee`}
                    value={`${wallet.attendee_first_name ?? ''} ${wallet.attendee_last_name ?? ''} (${wallet.attendee_public_id ?? ''})`}
                    disabled
                />

                <TextInput
                    label={t`Current balance`}
                    value={formatCurrency(wallet.balance, wallet.currency)}
                    disabled
                />

                <NumberInput
                    withAsterisk
                    label={t`Amount to add`}
                    min={0.01}
                    decimalScale={2}
                    {...form.getInputProps('amount')}
                />

                <Select
                    withAsterisk
                    label={t`How was this paid?`}
                    data={[
                        {value: 'CASH', label: t`Cash`},
                        {value: 'CARD_TERMINAL', label: t`Card terminal`},
                        {value: 'OTHER', label: t`Other`},
                    ]}
                    {...form.getInputProps('payment_method')}
                />

                <TextInput
                    label={t`Notes`}
                    placeholder={t`Optional note for your records`}
                    {...form.getInputProps('notes')}
                />

                <Button
                    type="submit"
                    fullWidth
                    mt="md"
                    loading={topupMutation.isPending}
                    data-testid="cashless-topup-submit-button"
                >
                    {t`Add funds`}
                </Button>
            </form>
        </Modal>
    );
};
