import {Event} from "../../../../../../types.ts";
import {Card} from "../../../../../common/Card";

interface OfflinePaymentMethodProps {
    event: Event;
}

export const OfflinePaymentMethod = ({event}: OfflinePaymentMethodProps) => {
    const eventSettings = event?.settings;

    return (
        <div>
            <h2>Offline Payment</h2>
            <Card>
                <div
                    dangerouslySetInnerHTML={{
                        __html: eventSettings?.offline_payment_instructions || "",
                    }}
                />
            </Card>
        </div>
    );
};
