import {t} from "@lingui/macro";
import {useParams} from "react-router";
import {useEffect, useState} from "react";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {useGetAccount} from "../../../../../../queries/useGetAccount.ts";
import {PlatformFeesSettings as PlatformFeesSettingsBase} from "../../../../../common/PlatformFeesSettings";

export const PlatformFeesSettings = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const accountQuery = useGetAccount();
    const updateMutation = useUpdateEventSettings();
    const [currentValue, setCurrentValue] = useState(false);

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data) {
            setCurrentValue(eventSettingsQuery.data.pass_platform_fee_to_buyer ?? false);
        }
    }, [eventSettingsQuery.isFetched, eventSettingsQuery.data]);

    const handleSave = (passToBuyer: boolean) => {
        updateMutation.mutate({
            eventSettings: { pass_platform_fee_to_buyer: passToBuyer },
            eventId: eventId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Platform Fee Settings`);
                setCurrentValue(passToBuyer);
            },
        });
    };

    return (
        <PlatformFeesSettingsBase
            configuration={accountQuery.data?.configuration}
            currentValue={currentValue}
            onSave={handleSave}
            isLoading={eventSettingsQuery.isLoading}
            isSaving={updateMutation.isPending}
            heading={t`Platform Fees`}
            description={t`Control how platform fees are handled for this event`}
            feeHandlingLabel={t`Fee Handling`}
            feeHandlingDescription={t`Choose who pays the platform fee. This does not affect additional fees you've configured in your account settings.`}
        />
    );
};
