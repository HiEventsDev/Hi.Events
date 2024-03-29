import {IconBrandFacebook, IconBrandLinkedin, IconBrandTwitter, IconBrandWhatsapp} from "@tabler/icons-react";
import {Anchor, UnstyledButton} from "@mantine/core";
import classes from "./ShareBar.module.scss";

interface ShareBarProps {
    url: string;
    title: string;
    description: string;
    imageUrl: string;
}

export const ShareBar = () => {
    return (
        <div className={classes.shareBar}>
            <div className={classes.whatsapp}>
                <Anchor
                    href={`https://wa.me/?text=${encodeURIComponent(window.location.href)}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Share on WhatsApp"
                >
                    <IconBrandWhatsapp color={'#fff'} size={24}/>
                </Anchor>
            </div>
            <div className={classes.facebook}>
                <Anchor
                    href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(window.location.href)}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Share on Facebook"
                >
                    <IconBrandFacebook color={'#fff'} size={24}/>
                </Anchor>
            </div>
            <div className={classes.twitter}>
                <Anchor
                    href={`https://twitter.com/intent/tweet?url=${encodeURIComponent(window.location.href)}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Share on Twitter"
                >
                    <IconBrandTwitter color={'#fff'} size={24}/>
                </Anchor>
            </div>
            <div className={classes.linkedin}>
                <Anchor
                    href={`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(window.location.href)}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Share on LinkedIn"
                >
                    <IconBrandLinkedin color={'#fff'} size={24}/>
                </Anchor>
            </div>
        </div>

    )
}