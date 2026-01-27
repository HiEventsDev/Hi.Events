import {PageBody} from "../../../common/PageBody";
import {EventDetailsForm} from "./Sections/EventDetailsForm";
import {LocationSettings} from "./Sections/LocationSettings";
import {HomepageAndCheckoutSettings} from "./Sections/HomepageAndCheckoutSettings";
import {EmailSettings} from "./Sections/EmailSettings";
import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {SeoSettings} from "./Sections/SeoSettings";
import {MiscSettings} from "./Sections/MiscSettings";
import {RegistrationSettings} from "./Sections/RegistrationSettings";
import {Box, Group, NavLink as MantineNavLink, Stack} from "@mantine/core";
import {
    IconAdjustments,
    IconAt,
    IconBrandGoogleAnalytics,
    IconBuildingStore,
    IconCreditCard,
    IconHome,
    IconMapPin,
    IconPercentage,
    IconTicket,
} from "@tabler/icons-react";
import {useMediaQuery} from "@mantine/hooks";
import {useMemo, useState} from "react";
import {Card} from "../../../common/Card";
import {PaymentAndInvoicingSettings} from "./Sections/PaymentSettings";
import {PlatformFeesSettings} from "./Sections/PlatformFeesSettings";
import {useGetAccount} from "../../../../queries/useGetAccount.ts";
import {useParams} from "react-router";
import {useGetEventSettings} from "../../../../queries/useGetEventSettings.ts";

export const Settings = () => {
    const {eventId} = useParams();
    const {data: account} = useGetAccount();
    const {data: eventSettings} = useGetEventSettings(eventId);
    const isSaasMode = account?.is_saas_mode_enabled;
    const isExternalRegistration = eventSettings?.is_external_registration;

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
                id: 'registration-settings',
                label: t`Registration`,
                icon: IconTicket,
                component: RegistrationSettings
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
                id: 'payment-settings',
                label: t`Payment & Invoicing`,
                icon: IconCreditCard,
                component: PaymentAndInvoicingSettings,
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

        // Hide certain sections when external registration is enabled
        if (isExternalRegistration) {
            return baseSections.filter(section =>
                !['homepage-settings', 'seo-settings', 'email-settings', 'misc-settings', 'payment-settings', 'platform-fees'].includes(section.id)
            );
        }

        return baseSections;
    }, [isSaasMode, isExternalRegistration]);

    const isLargeScreen = useMediaQuery('(min-width: 1200px)', true);
    const [activeSection, setActiveSection] = useState('event-details');

    const handleClick = (sectionId: string) => {
        setActiveSection(sectionId);
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
