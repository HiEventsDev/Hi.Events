import {useState} from 'react';
import {Button, CopyButton, Group, Menu, Modal, Paper, rem, Stack, Tabs, Text, TextInput, Tooltip} from '@mantine/core';
import {
    IconBrandFacebook,
    IconBrandLinkedin,
    IconBrandPinterest,
    IconBrandReddit,
    IconBrandTelegram,
    IconBrandWhatsapp,
    IconBrandX,
    IconCheck,
    IconCopy,
    IconDownload,
    IconLink,
    IconMail,
    IconQrcode,
    IconShare
} from '@tabler/icons-react';
import QRCode from 'react-qr-code';
import {Event} from "../../../types.ts";
import {t} from "@lingui/macro";
import {eventHomepageUrl} from "../../../utilites/urlHelper.ts";

interface ShareModalProps {
    event: Event;
    opened: boolean;
    onClose: () => void;
}

export const ShareModal = ({event, opened, onClose}: ShareModalProps) => {
    const [activeTab, setActiveTab] = useState<string | null>('share');
    const eventUrl = eventHomepageUrl(event);
    const shareText = event.title;

    const handleNativeShare = async () => {
        try {
            await navigator.share({
                title: event.title,
                text: shareText,
                url: eventUrl
            });
            onClose();
        } catch (error) {
            console.error('Share failed:', error);
        }
    };

    const handleSocialShare = (platform: string) => {
        const text = encodeURIComponent(shareText);
        const url = encodeURIComponent(eventUrl);
        let shareUrl = '';

        switch (platform) {
            case 'twitter':
                shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${text}`;
                break;
            case 'linkedin':
                shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
                break;
            case 'facebook':
                shareUrl = `https://www.facebook.com/sharer.php?u=${url}`;
                break;
            case 'whatsapp':
                shareUrl = `https://api.whatsapp.com/send?text=${text}%20${url}`;
                break;
            case 'telegram':
                shareUrl = `https://t.me/share/url?url=${url}&text=${text}`;
                break;
            case 'reddit':
                shareUrl = `https://reddit.com/submit?url=${url}&title=${text}`;
                break;
            case 'pinterest':
                shareUrl = `https://pinterest.com/pin/create/button/?url=${url}`;
                break;
            case 'email':
                shareUrl = `mailto:?subject=${text}&body=${url}`;
                break;
        }

        if (platform === 'email') {
            window.location.href = shareUrl;
        } else {
            window.open(shareUrl, '_blank', 'width=600,height=400');
        }
    };

    const downloadQR = () => {
        const svg = document.getElementById('event-qr-code');

        if (!svg) return;

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const img = new Image();

        img.onload = () => {
            canvas.width = img.width;
            canvas.height = img.height;
            ctx?.drawImage(img, 0, 0);

            const pngFile = canvas.toDataURL('image/png');
            const downloadLink = document.createElement('a');
            downloadLink.download = `${event.title}-qr-code.png`;
            downloadLink.href = pngFile;
            downloadLink.click();
        };

        const svgData = new XMLSerializer().serializeToString(svg);
        img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgData)));
    };

    return (
        <Modal
            opened={opened}
            onClose={onClose}
            title={t`Share Event`}
            centered
            size="md"
        >
            <Tabs value={activeTab} onChange={setActiveTab}>
                <Tabs.List>
                    <Tabs.Tab value="share" leftSection={<IconShare style={{width: rem(16)}}/>}>
                        {t`Share`}
                    </Tabs.Tab>
                    <Tabs.Tab value="qr" leftSection={<IconQrcode style={{width: rem(16)}}/>}>
                        {t`QR Code`}
                    </Tabs.Tab>
                    <Tabs.Tab value="url" leftSection={<IconLink style={{width: rem(16)}}/>}>
                        {t`URL`}
                    </Tabs.Tab>
                </Tabs.List>

                <Tabs.Panel value="share" pt="md">
                    <Stack>
                        <Group>
                            {navigator.share && (
                                <Button
                                    leftSection={<IconShare size={18}/>}
                                    onClick={handleNativeShare}
                                >
                                    {t`Share`}
                                </Button>
                            )}

                            <Menu position="bottom-end">
                                <Menu.Target>
                                    <Button variant="light">
                                        {t`Share to Social`}
                                    </Button>
                                </Menu.Target>

                                <Menu.Dropdown>
                                    <Menu.Item
                                        leftSection={<IconBrandX style={{width: rem(14)}}/>}
                                        onClick={() => handleSocialShare('twitter')}
                                    >
                                        {t`Share to X`}
                                    </Menu.Item>
                                    <Menu.Item
                                        leftSection={<IconBrandLinkedin style={{width: rem(14)}}/>}
                                        onClick={() => handleSocialShare('linkedin')}
                                    >
                                        {t`Share to LinkedIn`}
                                    </Menu.Item>
                                    <Menu.Item
                                        leftSection={<IconBrandFacebook style={{width: rem(14)}}/>}
                                        onClick={() => handleSocialShare('facebook')}
                                    >
                                        {t`Share to Facebook`}
                                    </Menu.Item>
                                    <Menu.Item
                                        leftSection={<IconBrandWhatsapp style={{width: rem(14)}}/>}
                                        onClick={() => handleSocialShare('whatsapp')}
                                    >
                                        {t`Share to WhatsApp`}
                                    </Menu.Item>
                                    <Menu.Item
                                        leftSection={<IconBrandTelegram style={{width: rem(14)}}/>}
                                        onClick={() => handleSocialShare('telegram')}
                                    >
                                        {t`Share to Telegram`}
                                    </Menu.Item>
                                    <Menu.Item
                                        leftSection={<IconBrandReddit style={{width: rem(14)}}/>}
                                        onClick={() => handleSocialShare('reddit')}
                                    >
                                        {t`Share to Reddit`}
                                    </Menu.Item>
                                    <Menu.Item
                                        leftSection={<IconBrandPinterest style={{width: rem(14)}}/>}
                                        onClick={() => handleSocialShare('pinterest')}
                                    >
                                        {t`Share to Pinterest`}
                                    </Menu.Item>
                                    <Menu.Item
                                        leftSection={<IconMail style={{width: rem(14)}}/>}
                                        onClick={() => handleSocialShare('email')}
                                    >
                                        {t`Share via Email`}
                                    </Menu.Item>
                                </Menu.Dropdown>
                            </Menu>
                        </Group>
                    </Stack>
                </Tabs.Panel>

                <Tabs.Panel value="qr" pt="md">
                    <Stack align="center">
                        <Paper withBorder p="xl" radius="md">
                            <QRCode
                                id="event-qr-code"
                                value={eventUrl}
                                size={200}
                                level="H"
                            />
                        </Paper>

                        <Group>
                            <Button
                                variant="light"
                                leftSection={<IconDownload size={16}/>}
                                onClick={downloadQR}
                            >
                                {t`Download QR Code`}
                            </Button>
                        </Group>

                        <Text size="sm" c="dimmed" ta="center">
                            {t`Scan this QR code to access the event page or share it with others`}
                        </Text>
                    </Stack>
                </Tabs.Panel>

                <Tabs.Panel value="url" pt="md">
                    <Stack>
                        <TextInput
                            label={t`Event URL`}
                            value={eventUrl}
                            readOnly
                            rightSection={
                                <CopyButton value={eventUrl} timeout={2000}>
                                    {({copied, copy}) => (
                                        <Tooltip label={copied ? t`Copied` : t`Copy URL`}>
                                            <Button
                                                variant="subtle"
                                                color={copied ? 'teal' : 'gray'}
                                                onClick={copy}
                                                leftSection={copied ? <IconCheck size={16}/> : <IconCopy size={16}/>}
                                            >
                                                {copied ? t`Copied` : t`Copy`}
                                            </Button>
                                        </Tooltip>
                                    )}
                                </CopyButton>
                            }
                        />
                    </Stack>
                </Tabs.Panel>
            </Tabs>
        </Modal>
    );
};
