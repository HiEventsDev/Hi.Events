import { SeoSettings } from "./Sections/SeoSettings";
import BasicSettings from "./Sections/BasicSettings";
import { SocialLinks } from "./Sections/SocialLinks";
import { AddressSettings } from "./Sections/AddressSettings";
import EmailTemplateSettings from "./Sections/EmailTemplateSettings";
import { EventDefaults } from "./Sections/EventDefaults";
import { PayoutsSettings } from "./Sections/PayoutsSettings";
import { PlatformFeesSettings } from "./Sections/PlatformFeesSettings";
import { DangerZoneSettings } from "./Sections/DangerZoneSettings";
import { TrackingPixelSettings } from "./Sections/TrackingPixelSettings";
import { PageBody } from "../../../common/PageBody";
import { PageTitle } from "../../../common/PageTitle";
import { t } from "@lingui/macro";
import { Box, Group, NavLink as MantineNavLink, Stack } from "@mantine/core";
import { IconAlertTriangle, IconBrandGoogleAnalytics, IconBrandStripe, IconInfoCircle, IconMapPin, IconShare, IconMail, IconCalendarEvent, IconPercentage, IconChartBar } from "@tabler/icons-react";
import { useMediaQuery } from "@mantine/hooks";
import { useEffect, useMemo, useState } from "react";
import { Card } from "../../../common/Card";
import { useLocation, useParams } from "react-router";
import { useGetAccount } from "../../../../queries/useGetAccount.ts";

const Settings = () => {
    const { organizerId } = useParams();
    const { data: account } = useGetAccount();
    const isSaasMode = account?.is_saas_mode_enabled;

    const SECTIONS = useMemo(() => {
        const baseSections = [
            {
                id: 'basic-settings',
                label: t`Basic Information`,
                icon: IconInfoCircle,
                component: BasicSettings
            },
            {
                id: 'event-defaults',
                label: t`Event Defaults`,
                icon: IconCalendarEvent,
                component: EventDefaults
            },
            {
                id: 'address-settings',
                label: t`Address`,
                icon: IconMapPin,
                component: AddressSettings
            },
            // {
            //     id: 'image-assets',
            //     label: t`Images & Branding`,
            //     icon: IconPhoto,
            //     component: ImageAssetSettings
            // },
            {
                id: 'social-links',
                label: t`Social Links`,
                icon: IconShare,
                component: SocialLinks
            },
            {
                id: 'seo-settings',
                label: t`SEO`,
                icon: IconBrandGoogleAnalytics,
                component: SeoSettings
            },
            {
                id: 'email-templates',
                label: t`Email Templates`,
                icon: IconMail,
                component: () => <EmailTemplateSettings organizerId={organizerId!} />
            },
            {
                id: 'tracking-pixels',
                label: t`Tracking & Analytics`,
                icon: IconChartBar,
                component: TrackingPixelSettings,
            },
            {
                id: 'danger-zone',
                label: t`Danger Zone`,
                icon: IconAlertTriangle,
                component: DangerZoneSettings,
                color: 'red',
            },
        ];

        if (isSaasMode) {
            baseSections.splice(2, 0,
                {
                    id: 'payouts',
                    label: t`Payouts`,
                    icon: IconBrandStripe,
                    component: PayoutsSettings,
                },
                {
                    id: 'platform-fees',
                    label: t`Platform Fees`,
                    icon: IconPercentage,
                    component: PlatformFeesSettings,
                });
        }

        return baseSections;
    }, [isSaasMode, organizerId]);

    const isLargeScreen = useMediaQuery('(min-width: 1200px)', true);
    const location = useLocation();
    const targetSectionId = useMemo(() => {
        const raw = location.hash?.replace(/^#/, '').split('?')[0] ?? '';
        return raw && SECTIONS.some(s => s.id === raw) ? raw : null;
    }, [location.hash, SECTIONS]);
    const [activeSection, setActiveSection] = useState(targetSectionId ?? 'basic-settings');

    useEffect(() => {
        if (!targetSectionId) return;
        setActiveSection(targetSectionId);

        const retryDelays = [0, 200, 600, 1200, 2000];
        const timers = retryDelays.map(delay => window.setTimeout(() => {
            document.getElementById(targetSectionId)?.scrollIntoView({ behavior: 'auto', block: 'start' });
        }, delay));

        return () => {
            timers.forEach(t => window.clearTimeout(t));
        };
    }, [targetSectionId]);

    const handleClick = (sectionId: string) => {
        setActiveSection(sectionId);
        document.getElementById(sectionId)?.scrollIntoView({ behavior: 'smooth' });
    };

    const sideMenu = (
        <Card style={{ padding: '15px', marginBottom: 0 }}>
            <Stack gap="xs">
                {SECTIONS.map((section) => (
                    <MantineNavLink
                        style={{ borderRadius: '5px' }}
                        key={section.id}
                        active={activeSection === section.id}
                        label={section.label}
                        color={'color' in section ? section.color as string : undefined}
                        leftSection={<section.icon size={16} stroke={1.5} />}
                        onClick={() => handleClick(section.id)}
                    />
                ))}
            </Stack>
        </Card>
    );

    const content = SECTIONS.map(({ id, component: Component }) => (
        <div key={id} id={id} style={{ scrollMarginTop: '20px' }}>
            <Component />
        </div>
    ));

    return (
        <PageBody>
            <PageTitle>{t`Organizer Settings`}</PageTitle>

            {isLargeScreen ? (
                <Group align="flex-start" gap="md">
                    <Box w={240} style={{ position: 'sticky', top: 20 }}>
                        {sideMenu}
                    </Box>
                    <Box style={{ flex: 1 }}>{content}</Box>
                </Group>
            ) : (
                <Stack>
                    {sideMenu}
                    {content}
                </Stack>
            )}
        </PageBody>
    );
}

export default Settings;
