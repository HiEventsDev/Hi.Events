import {PageBody} from "../../../common/PageBody";
import {EventDetailsForm} from "./Sections/EventDetailsForm";
import {LocationSettings} from "./Sections/LocationSettings";
import {HomepageAndCheckoutSettings} from "./Sections/HomepageAndCheckoutSettings";
import {EmailSettings} from "./Sections/EmailSettings";
import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {SeoSettings} from "./Sections/SeoSettings";
import {MiscSettings} from "./Sections/MiscSettings";
import {Box, Group, NavLink as MantineNavLink, Stack} from "@mantine/core";
import {
    IconAdjustments,
    IconAlertTriangle,
    IconAt,
    IconBrandGoogleAnalytics,
    IconBuildingStore,
    IconCreditCard,
    IconHome,
    IconListCheck,
    IconMapPin,
    IconPercentage,
} from "@tabler/icons-react";
import {useMediaQuery} from "@mantine/hooks";
import {useEffect, useMemo, useState} from "react";
import {Card} from "../../../common/Card";
import {PaymentAndInvoicingSettings} from "./Sections/PaymentSettings";
import {PlatformFeesSettings} from "./Sections/PlatformFeesSettings";
import {WaitlistSettings} from "./Sections/WaitlistSettings";
import {DangerZoneSettings} from "./Sections/DangerZoneSettings";
import {useGetAccount} from "../../../../queries/useGetAccount.ts";

export const Settings = () => {
    const {data: account} = useGetAccount();
    const isSaasMode = account?.is_saas_mode_enabled;

    const SECTIONS = useMemo(() => {
        const baseSections = [
            {
                id: 'event-details',
                label: t`Event Details`,
                icon: IconBuildingStore,
                component: EventDetailsForm
            },
            {
                id: 'location-settings',
                label: t`Location`,
                icon: IconMapPin,
                component: LocationSettings
            },
            {
                id: 'homepage-settings',
                label: t`Checkout`,
                icon: IconHome,
                component: HomepageAndCheckoutSettings
            },
            {
                id: 'seo-settings',
                label: t`SEO`,
                icon: IconBrandGoogleAnalytics,
                component: SeoSettings
            },
            {
                id: 'email-settings',
                label: t`Email & Templates`,
                icon: IconAt,
                component: EmailSettings
            },
            {
                id: 'misc-settings',
                label: t`Miscellaneous`,
                icon: IconAdjustments,
                component: MiscSettings
            },
            {
                id: 'waitlist-settings',
                label: t`Waitlist`,
                icon: IconListCheck,
                component: WaitlistSettings,
            },
            {
                id: 'payment-settings',
                label: t`Payment & Invoicing`,
                icon: IconCreditCard,
                component: PaymentAndInvoicingSettings,
            },
            {
                id: 'danger-zone',
                label: t`Danger Zone`,
                icon: IconAlertTriangle,
                component: DangerZoneSettings,
                color: 'red',
            }
        ];

        if (isSaasMode) {
            baseSections.splice(baseSections.length - 1, 0, {
                id: 'platform-fees',
                label: t`Platform Fees`,
                icon: IconPercentage,
                component: PlatformFeesSettings,
            });
        }

        return baseSections;
    }, [isSaasMode]);

    const isLargeScreen = useMediaQuery('(min-width: 1200px)', true);
    const [activeSection, setActiveSection] = useState(() => {
        if (typeof window === 'undefined') return 'event-details';
        const hash = window.location.hash.replace('#', '');
        return hash || 'event-details';
    });

    useEffect(() => {
        if (typeof window === 'undefined') return;
        const hash = window.location.hash.replace('#', '');
        if (hash) {
            setActiveSection(hash);
            setTimeout(() => {
                document.getElementById(hash)?.scrollIntoView({behavior: 'smooth'});
            }, 100);
        }
    }, []);

    const handleClick = (sectionId: string) => {
        setActiveSection(sectionId);
        window.history.replaceState(null, '', `#${sectionId}`);
        document.getElementById(sectionId)?.scrollIntoView({behavior: 'smooth'});
    };

    const sideMenu = (
        <Card style={{padding: '15px', marginBottom: 0}}>
            <Stack gap="xs">
                {SECTIONS.map((section) => (
                    <MantineNavLink
                        style={{borderRadius: '5px'}}
                        key={section.id}
                        active={activeSection === section.id}
                        label={section.label}
                        color={'color' in section ? section.color as string : undefined}
                        leftSection={<section.icon size={16} stroke={1.5}/>}
                        onClick={() => handleClick(section.id)}
                    />
                ))}
            </Stack>
        </Card>
    );

    const content = SECTIONS.map(({id, component: Component}) => (
        <div key={id} id={id} style={{scrollMarginTop: '20px'}}>
            <Component/>
        </div>
    ));

    return (
        <PageBody>
            <PageTitle
                subheading={t`Configure event details, location, checkout options, and email notifications.`}
            >{t`Event Settings`}</PageTitle>

            {isLargeScreen ? (
                <Group align="flex-start" gap="md">
                    <Box w={240} style={{position: 'sticky', top: 20}}>
                        {sideMenu}
                    </Box>
                    <Box style={{flex: 1}}>{content}</Box>
                </Group>
            ) : (
                <Stack>
                    {sideMenu}
                    {content}
                </Stack>
            )}
        </PageBody>
    );
};

export default Settings;
