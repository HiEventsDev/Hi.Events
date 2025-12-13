import {t} from "@lingui/macro";
import {useParams} from "react-router";
import {useEffect, useState} from "react";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useGetOrganizerSettings} from "../../../../../../queries/useGetOrganizerSettings.ts";
import {useUpdateOrganizerSettings} from "../../../../../../mutations/useUpdateOrganizerSettings.ts";
import {useGetAccount} from "../../../../../../queries/useGetAccount.ts";
import {PlatformFeesSettings as PlatformFeesSettingsBase} from "../../../../../common/PlatformFeesSettings";

export const PlatformFeesSettings = () => {
    const {organizerId} = useParams();
    const organizerSettingsQuery = useGetOrganizerSettings(organizerId);
    const accountQuery = useGetAccount();
    const updateMutation = useUpdateOrganizerSettings();
    const [currentValue, setCurrentValue] = useState(false);

    useEffect(() => {
        if (organizerSettingsQuery?.isFetched && organizerSettingsQuery?.data) {
            setCurrentValue(organizerSettingsQuery.data.default_pass_platform_fee_to_buyer ?? false);
        }
    }, [organizerSettingsQuery.isFetched, organizerSettingsQuery.data]);

    const handleSave = (passToBuyer: boolean) => {
        updateMutation.mutate({
            organizerSettings: { default_pass_platform_fee_to_buyer: passToBuyer },
            organizerId: organizerId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Platform Fee Defaults`);
                setCurrentValue(passToBuyer);
            },
        });
    };

    return (
        <PlatformFeesSettingsBase
            configuration={accountQuery.data?.configuration}
            currentValue={currentValue}
            onSave={handleSave}
            isLoading={organizerSettingsQuery.isLoading}
            isSaving={updateMutation.isPending}
            heading={t`Platform Fees`}
            description={t`Set default platform fee settings for new events created under this organizer.`}
            feeHandlingLabel={t`Default Fee Handling`}
            feeHandlingDescription={t`Choose the default setting for new events. This can be overridden for individual events.`}
        />
    );
};
