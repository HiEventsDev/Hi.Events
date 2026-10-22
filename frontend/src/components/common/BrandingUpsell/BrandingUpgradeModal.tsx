import {useState} from "react";
import {Button} from "@mantine/core";
import {t} from "@lingui/macro";
import {Modal, ModalFooter} from "../Modal";
import {SubscriptionBillingInterval} from "../../../types.ts";
import {useBrandingSubscription} from "../../../hooks/useBrandingSubscription.ts";
import {useSubscriptionRedirect} from "../../../hooks/useSubscriptionRedirect.ts";
import {BrandingAnimation} from "./BrandingAnimation.tsx";
import {IncludedFeatures} from "./IncludedFeatures.tsx";
import {defaultBillingInterval, PlanIntervalPicker} from "./PlanIntervalPicker.tsx";
import classes from "./BrandingUpsell.module.scss";

interface BrandingUpgradeModalProps {
    opened: boolean;
    onClose: () => void;
}

export const BrandingUpgradeModal = ({opened, onClose}: BrandingUpgradeModalProps) => {
    const {account, canManageBilling, prices} = useBrandingSubscription();
    const [billingInterval, setBillingInterval] = useState<SubscriptionBillingInterval>(() => defaultBillingInterval(prices));
    const {startCheckout, isStartingCheckout} = useSubscriptionRedirect(account?.id);

    return (
        <Modal opened={opened} onClose={onClose} size="lg" heading={t`Remove Hi.Events branding`}>
            <div className={classes.modalBody}>
                <BrandingAnimation/>
                <p className={classes.lead}>
                    {t`Make your event pages, widgets, tickets and emails look like they're entirely yours. Applies to every organizer and event in this account.`}
                </p>
                <IncludedFeatures/>
                {canManageBilling ? (
                    <PlanIntervalPicker value={billingInterval} onChange={setBillingInterval} prices={prices}/>
                ) : (
                    <p className={classes.askAdmin}>
                        {t`Only account admins can change billing. Ask an admin to upgrade from Settings → Billing.`}
                    </p>
                )}
            </div>
            <ModalFooter>
                <Button variant="default" onClick={onClose}>
                    {canManageBilling ? t`Not now` : t`Close`}
                </Button>
                {canManageBilling && (
                    <Button onClick={() => startCheckout(billingInterval)} loading={isStartingCheckout}>
                        {t`Continue to payment`}
                    </Button>
                )}
            </ModalFooter>
        </Modal>
    );
};
