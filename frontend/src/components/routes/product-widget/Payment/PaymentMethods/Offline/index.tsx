import {Event} from "../../../../../../types.ts";
import {Card} from "../../../../../common/Card";
import {t} from "@lingui/macro";

interface OfflinePaymentMethodProps {
    event: Event;
}

export const OfflinePaymentMethod = ({event}: OfflinePaymentMethodProps) => {
    const eventSettings = event?.settings;

    return (
        <div>
            <h2>{t`Payment Instructions`}</h2>
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
