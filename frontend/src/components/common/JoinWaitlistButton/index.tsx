import {Event, IdParam, Product} from "../../../types.ts";
import {useDisclosure} from "@mantine/hooks";
import {JoinWaitlistModal} from "../../modals/JoinWaitlistModal";
import {t} from "@lingui/macro";
import {useWaitlistJoined} from "../../../hooks/useWaitlistJoined.ts";

interface JoinWaitlistButtonProps {
    product: Product;
    event: Event;
    productPriceId: IdParam;
    priceLabel?: string;
    eventOccurrenceId?: IdParam;
}

export const JoinWaitlistButton = ({product, event, productPriceId, priceLabel, eventOccurrenceId}: JoinWaitlistButtonProps) => {
    const [modalOpen, {open: openModal, close: closeModal}] = useDisclosure(false);
    const {joined: hasJoined, markJoined} = useWaitlistJoined(event.id, productPriceId, eventOccurrenceId);

    return (
        <>
            <button
                type="button"
                className="hi-waitlist-button"
                data-testid="join-waitlist-button"
                onClick={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    openModal();
                }}
                disabled={hasJoined}
            >
                {hasJoined ? t`Joined` : t`Join Waitlist`}
            </button>
            {modalOpen && (
                <JoinWaitlistModal
                    onClose={closeModal}
                    isOpen
                    product={product}
                    event={event}
                    productPriceId={productPriceId}
                    priceLabel={priceLabel}
                    eventOccurrenceId={eventOccurrenceId}
                    onSuccess={() => {
                        markJoined();
                        closeModal();
                    }}
                />
            )}
        </>
    );
};
