import {useState} from 'react';
import {ActionIcon, CopyButton, Group, Input, Popover} from '@mantine/core';
import {
    IconBrandFacebook,
    IconBrandInstagram,
    IconBrandLinkedin,
    IconBrandTwitter,
    IconBrandWhatsapp, IconCheck, IconCopy,
    IconShare
} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {Button} from "@mantine/core";

interface ShareComponentProps {
    title: string;
    text: string;
    url: string;
}

export const ShareComponent = ({title, text, url}: ShareComponentProps) => {
    const [opened, setOpened] = useState(false);

    const shareData = {
        title,
        text,
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
                    <Button variant={'transparent'} leftSection={<IconShare size={20}/>} onClick={handleShareClick}>
                        {t`Share`}
                    </Button>
                </Popover.Target>

                <Popover.Dropdown>
                    <Group>
                        <ActionIcon variant={'transparent'}  component="a"
                                    href={`https://twitter.com/intent/tweet?text=${encodeURIComponent(shareData.text)}&url=${encodeURIComponent(shareData.url)}`}
                                    target="_blank" rel="noopener noreferrer">
                            <IconBrandTwitter size={24}/>
                        </ActionIcon>
                        <ActionIcon variant={'transparent'} component="a"
                                    href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareData.url)}`}
                                    target="_blank" rel="noopener noreferrer">
                            <IconBrandFacebook size={24}/>
                        </ActionIcon>
                        <ActionIcon variant={'transparent'} component="a"
                                    href={`https://www.instagram.com/?url=${encodeURIComponent(shareData.url)}`}
                                    target="_blank" rel="noopener noreferrer">
                            <IconBrandInstagram size={24}/>
                        </ActionIcon>
                        <ActionIcon variant={'transparent'} component="a"
                                    href={`https://www.linkedin.com/shareArticle?mini=true&url=${encodeURIComponent(shareData.url)}&title=${encodeURIComponent(shareData.title)}&summary=${encodeURIComponent(shareData.text)}&source=${encodeURIComponent(window.location.hostname)}`}
                                    target="_blank" rel="noopener noreferrer">
                            <IconBrandLinkedin size={24}/>
                        </ActionIcon>
                        <ActionIcon variant={'transparent'} component="a"
                                    href={`https://api.whatsapp.com/send?text=${encodeURIComponent(`${shareData.text} ${shareData.url}`)}`}
                                    target="_blank" rel="noopener noreferrer">
                            <IconBrandWhatsapp size={24}/>
                        </ActionIcon>
                    </Group>
                    <Input mt={10} value={shareData.url} rightSection={(
                        <CopyButton  value={shareData.url}>
                            {({ copied, copy }) => (
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
