import {useState} from "react";
import {Anchor, Switch} from "@mantine/core";
import {t} from "@lingui/macro";
import {NavLink, useLocation} from "react-router";
import {useBrandingSubscription} from "../../../hooks/useBrandingSubscription.ts";
import {BrandingUpgradeModal} from "./BrandingUpgradeModal.tsx";

export const BrandingSwitch = () => {
    const location = useLocation();
    const {isActive, canManageBilling} = useBrandingSubscription();
    const [modalOpen, setModalOpen] = useState(false);

    const description = isActive ? (
        <>
            {t`Included in your plan. Hi.Events branding is hidden on your event pages, widgets, tickets and emails.`}
            {canManageBilling && (
                <>
                    {' '}
                    <Anchor component={NavLink} to="/settings/billing" state={{returnTo: location.pathname}} size="xs">
                        {t`Manage billing`}
                    </Anchor>
                </>
            )}
        </>
    ) : t`Remove "Powered by Hi.Events" from your pages, widgets, tickets and emails, and show your own logo instead.`;

    return (
        <>
            <Switch
                label={t`Hide Hi.Events branding`}
                description={description}
                checked={isActive}
                disabled={isActive}
                aria-haspopup={isActive ? undefined : 'dialog'}
                onChange={() => setModalOpen(true)}
            />
            <BrandingUpgradeModal opened={modalOpen} onClose={() => setModalOpen(false)}/>
        </>
    );
};
