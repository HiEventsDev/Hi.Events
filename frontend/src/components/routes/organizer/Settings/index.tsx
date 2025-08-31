import {SeoSettings} from "./Sections/SeoSettings";
import BasicSettings from "./Sections/BasicSettings";
import {SocialLinks} from "./Sections/SocialLinks";
import {AddressSettings} from "./Sections/AddressSettings";
import EmailTemplateSettings from "./Sections/EmailTemplateSettings";
import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {Box, Group, NavLink as MantineNavLink, Stack} from "@mantine/core";
import {IconBrandGoogleAnalytics, IconInfoCircle, IconMapPin, IconShare, IconMail} from "@tabler/icons-react";
import {useMediaQuery} from "@mantine/hooks";
import {useState} from "react";
import {Card} from "../../../common/Card";
import {useParams} from "react-router";

const Settings = () => {
    const { organizerId } = useParams();
    
    const SECTIONS = [
        {
            id: 'basic-settings',
            label: t`Basic Information`,
            icon: IconInfoCircle,
            component: BasicSettings
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
    ];

    const isLargeScreen = useMediaQuery('(min-width: 1200px)', true);
    const [activeSection, setActiveSection] = useState(SECTIONS[0].id);

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
            <PageTitle>{t`Organizer Settings`}</PageTitle>

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
}

export default Settings;
