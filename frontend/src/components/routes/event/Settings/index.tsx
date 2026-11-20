import {useAccess} from "../../../../hooks/useAccess.ts";
import {EventDetailsForm} from "./Sections/EventDetailsForm";
import {EventTypeSettings} from "./Sections/EventTypeSettings";
import {LocationSettings} from "./Sections/LocationSettings";
import {HomepageAndCheckoutSettings} from "./Sections/HomepageAndCheckoutSettings";
import {EmailSettings} from "./Sections/EmailSettings";
import {t} from "@lingui/macro";
import {SeoSettings} from "./Sections/SeoSettings";
import {
    IconAlertTriangle,
    IconAt,
    IconBrandGoogleAnalytics,
    IconBuildingStore,
    IconCalendarRepeat,
    IconCreditCard,
    IconListCheck,
    IconMapPin,
    IconPercentage,
    IconRepeat,
    IconShoppingCart,
} from "@tabler/icons-react";
import {useMemo} from "react";
import {PaymentAndInvoicingSettings} from "./Sections/PaymentSettings";
import {PlatformFeesSettings} from "./Sections/PlatformFeesSettings";
import {WaitlistSettings} from "./Sections/WaitlistSettings";
import {RecurringEventSettings} from "./Sections/RecurringEventSettings";
import {DangerZoneSettings} from "./Sections/DangerZoneSettings";
import {useGetAccount} from "../../../../queries/useGetAccount.ts";
import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {useParams} from "react-router";
import {EventType} from "../../../../types.ts";
import {SectionedSettings, SettingsSectionGroup} from "../../../common/SectionedSettings";

export const Settings = () => {
    const {data: account, isFetched: isAccountFetched} = useGetAccount();
    const isSaasMode = account?.is_saas_mode_enabled;
    const {eventId} = useParams();
    const {data: event, isFetched: isEventFetched} = useGetEvent(eventId);
    const isRecurring = event?.type === EventType.RECURRING;
    const {can, isLoaded: isAccessLoaded} = useAccess();
    const showPlatformFees = !!isSaasMode && can('VIEW_FINANCIALS');

    const groups = useMemo<SettingsSectionGroup[]>(() => [
        {
            label: t`General`,
            sections: [
                {
                    slug: 'details',
                    anchorId: 'event-details',
                    label: t`Event Details`,
                    icon: IconBuildingStore,
                    component: EventDetailsForm,
                },
                {
                    slug: 'event-type',
                    anchorId: 'event-type',
                    label: t`Event Type`,
                    icon: IconCalendarRepeat,
                    component: EventTypeSettings,
                },
                {
                    slug: 'location',
                    anchorId: 'location-settings',
                    label: t`Location`,
                    icon: IconMapPin,
                    component: LocationSettings,
                },
                ...(isRecurring ? [{
                    slug: 'recurring',
                    anchorId: 'recurring-event-settings',
                    label: t`Recurring Event`,
                    icon: IconRepeat,
                    component: RecurringEventSettings,
                }] : []),
            ],
        },
        {
            label: t`Ticket sales`,
            sections: [
                {
                    slug: 'checkout',
                    anchorId: 'homepage-settings',
                    label: t`Checkout`,
                    icon: IconShoppingCart,
                    component: HomepageAndCheckoutSettings,
                },
                {
                    slug: 'waitlist',
                    anchorId: 'waitlist-settings',
                    label: t`Waitlist`,
                    icon: IconListCheck,
                    component: WaitlistSettings,
                },
                {
                    slug: 'payments',
                    anchorId: 'payment-settings',
                    label: t`Payment & Invoicing`,
                    icon: IconCreditCard,
                    component: PaymentAndInvoicingSettings,
                },
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
                    slug: 'email',
                    anchorId: 'email-settings',
                    label: t`Email & Templates`,
                    icon: IconAt,
                    component: EmailSettings,
                },
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
                    slug: 'danger-zone',
                    anchorId: 'danger-zone',
                    label: t`Danger Zone`,
                    icon: IconAlertTriangle,
                    component: DangerZoneSettings,
                    tone: 'danger',
                },
            ],
        },
    ], [showPlatformFees, isRecurring]);

    return (
        <SectionedSettings
            navLabel={t`Event Settings`}
            basePath={`/manage/event/${eventId}/settings`}
            groups={groups}
            isReady={isAccountFetched && isEventFetched && isAccessLoaded}
        />
    );
};

export default Settings;
