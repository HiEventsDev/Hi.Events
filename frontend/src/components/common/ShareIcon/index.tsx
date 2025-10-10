import {useState} from 'react';
import {ActionIcon, Button, CopyButton, Group, Input, Popover} from '@mantine/core';
import {
    IconBrandFacebook,
    IconBrandLinkedin,
    IconBrandTwitter,
    IconBrandWhatsapp,
    IconCheck,
    IconCopy,
    IconMail,
    IconShare
} from "@tabler/icons-react";
import {t} from "@lingui/macro";

interface ShareComponentProps {
    title: string;
    text: string;
    url: string;
    imageUrl?: string;
    shareButtonText?: string;
    hideShareButtonText?: boolean;
    className?: string;
}

export const ShareComponent = ({
                                   title,
                                   text,
                                   url,
                                   shareButtonText = t`Share`,
                                   hideShareButtonText = false,
                                   className,
                               }: ShareComponentProps) => {
    const [opened, setOpened] = useState(false);

    let shareText = text;

    const shareData = {
        title,
        text: shareText,
        url,
    };

    const handleShareClick = async () => {
        if (navigator.share) {
            try {
                await navigator.share(shareData);
            } catch (error) {
                console.error('Error sharing:', error);
            }
        } else {
            setOpened(!opened);
        }
    };

    return (
        <Popover
            opened={opened}
            onClose={() => setOpened(false)}
            position="bottom"
            withArrow
        >
            <Popover.Target>
                <div style={{display: 'flex'}}>
                    {hideShareButtonText && (
                        <ActionIcon variant={'transparent'} onClick={handleShareClick} className={className}>
                            <IconShare size={20}/>
                        </ActionIcon>
                    )}

                    {!hideShareButtonText && (
                        <Button variant={'transparent'} leftSection={<IconShare size={20}/>} onClick={handleShareClick} className={className}>
                            {hideShareButtonText ? '' : shareButtonText}
                        </Button>)}
                </div>
            </Popover.Target>

            <Popover.Dropdown style={{display: 'flex'}}>
                <Group>
                    <ActionIcon variant={'transparent'} component="a"
                                href={`https://twitter.com/intent/tweet?text=${encodeURIComponent(shareText)}&url=${encodeURIComponent(url)}`}
                                target="_blank" rel="noopener noreferrer">
                        <IconBrandTwitter size={24}/>
                    </ActionIcon>
                    <ActionIcon variant={'transparent'} component="a"
                                href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`}
                                target="_blank" rel="noopener noreferrer">
                        <IconBrandFacebook size={24}/>
                    </ActionIcon>
                    <ActionIcon variant={'transparent'} component="a"
                                href={`https://www.linkedin.com/shareArticle?mini=true&url=${encodeURIComponent(url)}&title=${encodeURIComponent(title)}&summary=${encodeURIComponent(shareText)}&source=${encodeURIComponent(typeof window !== "undefined" ? window?.location?.hostname : "")}`}
                                target="_blank" rel="noopener noreferrer">
                        <IconBrandLinkedin size={24}/>
                    </ActionIcon>
                    <ActionIcon variant={'transparent'} component="a"
                                href={`https://api.whatsapp.com/send?text=${encodeURIComponent(`${shareText} ${url}`)}`}
                                target="_blank" rel="noopener noreferrer">
                        <IconBrandWhatsapp size={24}/>
                    </ActionIcon>
                    <ActionIcon variant={'transparent'} component="a"
                                href={`mailto:?subject=${encodeURIComponent(title)}&body=${url}`}
                                target="_blank" rel="noopener noreferrer">
                        <IconMail size={24}/>
                    </ActionIcon>
                </Group>
                <Input rightSectionPointerEvents={'all'} mt={10} value={url} rightSection={(
                    <CopyButton value={url}>
                        {({copied, copy}) => (
                            <ActionIcon variant={'transparent'} onClick={copy}>
                                {copied ? <IconCheck/> : <IconCopy/>}
                            </ActionIcon>
                        )}
                    </CopyButton>
                )}/>
            </Popover.Dropdown>
        </Popover>
    );
};
