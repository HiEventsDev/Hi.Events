import { SeoSettings } from "./Sections/SeoSettings";
import BasicSettings from "./Sections/BasicSettings";
import { SocialLinks } from "./Sections/SocialLinks";
import { AddressSettings } from "./Sections/AddressSettings";
import EmailTemplateSettings from "./Sections/EmailTemplateSettings";
import { EventDefaults } from "./Sections/EventDefaults";
import { PayoutsSettings } from "./Sections/PayoutsSettings";
import { PlatformFeesSettings } from "./Sections/PlatformFeesSettings";
import { DangerZoneSettings } from "./Sections/DangerZoneSettings";
import { TerminalReadersSettings } from "../../../../ee/box-office/components/routes/organizer/Payments/TerminalReaders";
import { TrackingPixelSettings } from "./Sections/TrackingPixelSettings";
import { t } from "@lingui/macro";
import {IconAlertTriangle, IconDeviceMobile, IconBrandGoogleAnalytics, IconBrandStripe, IconInfoCircle, IconMapPin, IconShare, IconMail, IconCalendarEvent, IconPercentage, IconChartBar, IconMessage} from "@tabler/icons-react";
import { useMemo } from "react";
import { useParams } from "react-router";
import {SectionedSettings, SettingsSectionGroup} from "../../../common/SectionedSettings";
import { useGetAccount } from "../../../../queries/useGetAccount.ts";
import { useAccess } from "../../../../hooks/useAccess.ts";
import { FeatureFlag } from "../../../../constants/featureFlags.ts";
import { useLicensedFeature } from "../../../../ee/licensing/hooks/useLicensedFeature.ts";
import {SmsSettings} from "../../../../ee/crm/components/SmsSettings";
import {useTextMessageSettingsVisible} from "../../../../ee/crm/hooks/useTextMessageSettingsVisible.ts";

const Settings = () => {
    const { organizerId } = useParams();
    const { data: account, isFetched: isAccountFetched } = useGetAccount();
    const isSaasMode = !!account?.is_saas_mode_enabled;
    const {can, isLoaded: isAccessLoaded} = useAccess();
    const canManagePayments = can('MANAGE_PAYMENTS');
    const showPayouts = isSaasMode && canManagePayments;
    const showPlatformFees = isSaasMode && can('VIEW_FINANCIALS');
    const showCardReaders = useLicensedFeature(FeatureFlag.BOX_OFFICE).isVisible && canManagePayments;
    const {isVisible: showTextMessages, isResolved: isTextMessagesResolved} = useTextMessageSettingsVisible(organizerId);

    const groups = useMemo<SettingsSectionGroup[]>(() => [
        {
            label: t`General`,
            sections: [
                {
                    slug: 'basic',
                    anchorId: 'basic-settings',
                    label: t`Basic Information`,
                    icon: IconInfoCircle,
                    component: BasicSettings,
                },
                {
                    slug: 'address',
                    anchorId: 'address-settings',
                    label: t`Address`,
                    icon: IconMapPin,
                    component: AddressSettings,
                },
                {
                    slug: 'social-links',
                    anchorId: 'social-links',
                    label: t`Social Links`,
                    icon: IconShare,
                    component: SocialLinks,
                },
                {
                    slug: 'event-defaults',
                    anchorId: 'event-defaults',
                    label: t`Event Defaults`,
                    icon: IconCalendarEvent,
                    component: EventDefaults,
                },
            ],
        },
        {
            label: t`Payments`,
            sections: [
                ...(showPayouts ? [{
                    slug: 'payouts',
                    anchorId: 'payouts',
                    label: t`Payouts`,
                    icon: IconBrandStripe,
                    component: PayoutsSettings,
                }] : []),
                ...(showCardReaders ? [{
                    slug: 'card-readers',
                    anchorId: 'card-readers',
                    label: t`Card Readers`,
                    icon: IconDeviceMobile,
                    component: TerminalReadersSettings,
                }] : []),
                ...(showPlatformFees ? [{
                    slug: 'platform-fees',
                    anchorId: 'platform-fees',
                    label: t`Platform Fees`,
                    icon: IconPercentage,
                    component: PlatformFeesSettings,
                }] : []),
            ],
        },
        {
            label: t`Communications`,
            sections: [
                {
                    slug: 'email-templates',
                    anchorId: 'email-templates',
                    label: t`Email Templates`,
                    icon: IconMail,
                    component: () => <EmailTemplateSettings organizerId={organizerId!} />,
                },
                ...(showTextMessages ? [{
                    slug: 'text-messages',
                    anchorId: 'text-messages',
                    label: t`Text Messages`,
                    icon: IconMessage,
                    component: () => <SmsSettings organizerId={organizerId!}/>,
                }] : []),
            ],
        },
        {
            label: t`Advanced`,
            sections: [
                {
                    slug: 'seo',
                    anchorId: 'seo-settings',
                    label: t`SEO`,
                    icon: IconBrandGoogleAnalytics,
                    component: SeoSettings,
                },
                {
                    slug: 'tracking',
                    anchorId: 'tracking-pixels',
                    label: t`Tracking & Analytics`,
                    icon: IconChartBar,
                    component: TrackingPixelSettings,
                },
                {
                    slug: 'danger-zone',
                    anchorId: 'danger-zone',
                    label: t`Danger Zone`,
                    icon: IconAlertTriangle,
                    component: DangerZoneSettings,
                    tone: 'danger',
                },
            ],
        },
    ], [showPayouts, showCardReaders, showPlatformFees, showTextMessages, organizerId]);

    return (
        <SectionedSettings
            navLabel={t`Organizer Settings`}
            basePath={`/manage/organizer/${organizerId}/settings`}
            groups={groups}
            isReady={isAccountFetched && isAccessLoaded && isTextMessagesResolved}
        />
    );
}

export default Settings;
