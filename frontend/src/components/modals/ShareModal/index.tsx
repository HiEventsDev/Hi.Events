import {useState} from 'react';
import {Button, CopyButton, Modal, Paper, Stack, Tabs, Text, TextInput, Tooltip, UnstyledButton} from '@mantine/core';
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
import {t} from "@lingui/macro";
import classes from './ShareModal.module.scss';

interface ShareModalProps {
    opened: boolean;
    onClose: () => void;
    url: string;
    title: string;
    modalTitle?: string;
    shareText?: string;
}

export const ShareModal = ({
    opened,
    onClose,
    url,
    title,
    modalTitle = t`Share`,
    shareText
}: ShareModalProps) => {
    const [activeTab, setActiveTab] = useState<string | null>('share');
    const shareTextContent = shareText || title;

    const handleNativeShare = async () => {
        try {
            await navigator.share({
                title: title,
                text: shareTextContent,
                url: url
            });
            onClose();
        } catch (error) {
            console.error('Share failed:', error);
        }
    };

    const socialPlatforms = [
        {
            name: 'X',
            icon: IconBrandX,
            color: '#000000',
            shareUrl: (text: string, shareUrl: string) => 
                `https://twitter.com/intent/tweet?url=${shareUrl}&text=${text}`
        },
        {
            name: 'LinkedIn',
            icon: IconBrandLinkedin,
            color: '#0077b5',
            shareUrl: (_text: string, shareUrl: string) => 
                `https://www.linkedin.com/sharing/share-offsite/?url=${shareUrl}`
        },
        {
            name: 'Facebook',
            icon: IconBrandFacebook,
            color: '#1877f2',
            shareUrl: (_text: string, shareUrl: string) => 
                `https://www.facebook.com/sharer.php?u=${shareUrl}`
        },
        {
            name: 'WhatsApp',
            icon: IconBrandWhatsapp,
            color: '#25d366',
            shareUrl: (text: string, shareUrl: string) => 
                `https://api.whatsapp.com/send?text=${text}%20${shareUrl}`
        },
        {
            name: 'Telegram',
            icon: IconBrandTelegram,
            color: '#0088cc',
            shareUrl: (text: string, shareUrl: string) => 
                `https://t.me/share/url?url=${shareUrl}&text=${text}`
        },
        {
            name: 'Reddit',
            icon: IconBrandReddit,
            color: '#ff4500',
            shareUrl: (text: string, shareUrl: string) => 
                `https://reddit.com/submit?url=${shareUrl}&title=${text}`
        },
        {
            name: 'Pinterest',
            icon: IconBrandPinterest,
            color: '#bd081c',
            shareUrl: (_text: string, shareUrl: string) => 
                `https://pinterest.com/pin/create/button/?url=${shareUrl}`
        },
        {
            name: 'Email',
            icon: IconMail,
            color: '#6b7280',
            shareUrl: (text: string, shareUrl: string) => 
                `mailto:?subject=${text}&body=${shareUrl}`,
            isEmail: true
        }
    ];

    const handleSocialShare = (platform: typeof socialPlatforms[0]) => {
        const text = encodeURIComponent(shareTextContent);
        const encodedUrl = encodeURIComponent(url);
        const shareUrl = platform.shareUrl(text, encodedUrl);

        if (platform.isEmail) {
            window.location.href = shareUrl;
        } else {
            window.open(shareUrl, '_blank', 'width=600,height=400');
        }
    };

    const downloadQR = () => {
        const svg = document.getElementById('share-qr-code');

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
            downloadLink.download = `${title}-qr-code.png`;
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
            title={modalTitle}
            centered
            size="lg"
            className={classes.modal}
        >
            <Tabs value={activeTab} onChange={setActiveTab}>
                <Tabs.List>
                    <Tabs.Tab value="share" leftSection={<IconShare size={16}/>}>
                        {t`Share`}
                    </Tabs.Tab>
                    <Tabs.Tab value="qr" leftSection={<IconQrcode size={16}/>}>
                        {t`QR Code`}
                    </Tabs.Tab>
                    <Tabs.Tab value="url" leftSection={<IconLink size={16}/>}>
                        {t`Copy Link`}
                    </Tabs.Tab>
                </Tabs.List>

                <div className={classes.tabsContent}>
                    <Tabs.Panel value="share" pt="lg">
                        <Stack>
                            {navigator.share && (
                                <Button
                                    size="lg"
                                    variant={'outline'}
                                    leftSection={<IconShare size={20}/>}
                                    onClick={handleNativeShare}
                                    className={classes.nativeShareButton}
                                >
                                    {t`Share`}
                                </Button>
                            )}

                            <div className={classes.socialGrid}>
                                {socialPlatforms.map((platform) => (
                                    <UnstyledButton
                                        key={platform.name}
                                        onClick={() => handleSocialShare(platform)}
                                        className={classes.socialButton}
                                    >
                                        <div 
                                            className={classes.socialIcon}
                                            style={{ color: platform.color }}
                                        >
                                            <platform.icon size={24} stroke={1.5}/>
                                        </div>
                                        <Text className={classes.socialLabel}>
                                            {platform.name}
                                        </Text>
                                    </UnstyledButton>
                                ))}
                            </div>
                        </Stack>
                    </Tabs.Panel>

                    <Tabs.Panel value="qr" pt="lg">
                        <div className={classes.qrContainer}>
                            <Paper className={classes.qrCodeWrapper}>
                                <QRCode
                                    id="share-qr-code"
                                    value={url}
                                    size={200}
                                    level="H"
                                />
                            </Paper>

                            <Button
                                variant="light"
                                leftSection={<IconDownload size={16}/>}
                                onClick={downloadQR}
                            >
                                {t`Download QR Code`}
                            </Button>
                        </div>
                    </Tabs.Panel>

                    <Tabs.Panel value="url" pt="lg">
                        <div className={classes.urlSection}>
                            <TextInput
                                label={t`Page URL`}
                                value={url}
                                readOnly
                                styles={{
                                    input: {
                                        paddingRight: '120px'
                                    }
                                }}
                                rightSectionWidth={'auto'}
                                rightSection={(
                                    <CopyButton value={url} timeout={2000}>
                                        {({copied, copy}) => (
                                            <Tooltip label={copied ? t`Copied!` : t`Copy to clipboard`}>
                                                <Button
                                                    variant="light"
                                                    color={copied ? 'teal' : 'gray'}
                                                    onClick={copy}
                                                    leftSection={copied ? <IconCheck size={16}/> : <IconCopy size={16}/>}
                                                >
                                                    {copied ? t`Copied` : t`Copy`}
                                                </Button>
                                            </Tooltip>
                                        )}
                                    </CopyButton>
                                )}
                            />

                            <Text className={classes.helperText} mt="md">
                                {t`Copy this link to share it anywhere`}
                            </Text>
                        </div>
                    </Tabs.Panel>
                </div>
            </Tabs>
        </Modal>
    );
};
