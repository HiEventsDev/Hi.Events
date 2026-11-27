import {
    IconBrandFacebook,
    IconBrandLinkedin,
    IconBrandPinterest,
    IconBrandReddit,
    IconBrandTelegram,
    IconBrandWhatsapp,
    IconBrandX,
    IconMail,
} from "@tabler/icons-react";
import {ComponentType} from "react";

export interface SharePlatform {
    name: string;
    icon: ComponentType<{ size?: number; stroke?: number }>;
    color: string;
    carriesCaption: boolean;
    opensMailClient?: boolean;
    shareUrl: (encodedCaption: string, encodedUrl: string) => string;
}

export const X_POST_CAPTION_LIMIT = 257;

export const SHARE_PLATFORMS: SharePlatform[] = [
    {
        name: 'X',
        icon: IconBrandX,
        color: '#000000',
        carriesCaption: true,
        shareUrl: (caption, url) => `https://twitter.com/intent/tweet?url=${url}&text=${caption}`,
    },
    {
        name: 'LinkedIn',
        icon: IconBrandLinkedin,
        color: '#0077b5',
        carriesCaption: false,
        shareUrl: (_caption, url) => `https://www.linkedin.com/sharing/share-offsite/?url=${url}`,
    },
    {
        name: 'Facebook',
        icon: IconBrandFacebook,
        color: '#1877f2',
        carriesCaption: false,
        shareUrl: (_caption, url) => `https://www.facebook.com/sharer.php?u=${url}`,
    },
    {
        name: 'WhatsApp',
        icon: IconBrandWhatsapp,
        color: '#128c7e',
        carriesCaption: true,
        shareUrl: (caption, url) => `https://api.whatsapp.com/send?text=${caption}%20${url}`,
    },
    {
        name: 'Telegram',
        icon: IconBrandTelegram,
        color: '#0088cc',
        carriesCaption: true,
        shareUrl: (caption, url) => `https://t.me/share/url?url=${url}&text=${caption}`,
    },
    {
        name: 'Reddit',
        icon: IconBrandReddit,
        color: '#ff4500',
        carriesCaption: true,
        shareUrl: (caption, url) => `https://reddit.com/submit?url=${url}&title=${caption}`,
    },
    {
        name: 'Pinterest',
        icon: IconBrandPinterest,
        color: '#bd081c',
        carriesCaption: false,
        shareUrl: (_caption, url) => `https://pinterest.com/pin/create/button/?url=${url}`,
    },
    {
        name: 'Email',
        icon: IconMail,
        color: '#4b5563',
        carriesCaption: true,
        opensMailClient: true,
        shareUrl: (caption, url) => `mailto:?subject=${caption}&body=${url}`,
    },
];
