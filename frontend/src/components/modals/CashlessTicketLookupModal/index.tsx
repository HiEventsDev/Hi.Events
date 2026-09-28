import {t} from "@lingui/macro";
import {Button, TextInput} from "@mantine/core";
import {IconScan} from "@tabler/icons-react";
import {useState} from "react";
import {useParams} from "react-router";
import {Modal} from "../../common/Modal";
import {CashlessTopupModal} from "../CashlessTopupModal";
import {CashlessWallet, GenericModalProps} from "../../../types.ts";
import {useLookupCashlessWallet} from "../../../mutations/useLookupCashlessWallet.ts";
import {showError} from "../../../utilites/notifications.tsx";

export const CashlessTicketLookupModal = ({onClose}: GenericModalProps) => {
    const {eventId} = useParams();
    const [ticketId, setTicketId] = useState('');
    const [wallet, setWallet] = useState<CashlessWallet>();
    const lookupMutation = useLookupCashlessWallet();

    const handleLookup = (event: React.FormEvent) => {
        event.preventDefault();

        lookupMutation.mutate({eventId, attendeePublicId: ticketId.trim()}, {
            onSuccess: ({data}) => setWallet(data),
            onError: (error: any) => showError(
                error?.response?.data?.message || t`No ticket was found for this code.`
            ),
        });
    };

    if (wallet) {
        return <CashlessTopupModal wallet={wallet} onClose={onClose}/>;
    }

    return (
        <Modal opened onClose={onClose} heading={t`Top up a ticket`}>
            <form onSubmit={handleLookup}>
                <TextInput
                    autoFocus
                    withAsterisk
                    label={t`Ticket ID`}
                    description={t`Scan the ticket QR code with a handheld scanner, or type the ID printed on the ticket.`}
                    placeholder="A-XXXXXXX"
                    leftSection={<IconScan size={16}/>}
                    value={ticketId}
                    onChange={(event) => setTicketId(event.currentTarget.value)}
                    data-testid="cashless-ticket-lookup-input"
                />

                <Button
                    type="submit"
                    fullWidth
                    mt="md"
                    disabled={!ticketId.trim()}
                    loading={lookupMutation.isPending}
                    data-testid="cashless-ticket-lookup-submit-button"
                >
                    {t`Find ticket`}
                </Button>
            </form>
        </Modal>
    );
};
