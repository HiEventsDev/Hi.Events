import {useState} from 'react';
import {t} from '@lingui/macro';
import {getConfig} from '../../../utilites/config';
import classes from './CookieConsentBanner.module.scss';
import {IconCookie, IconX} from '@tabler/icons-react';

interface CookieConsentBannerProps {
    onConsent: (granted: boolean) => void;
}

export const CookieConsentBanner = ({onConsent}: CookieConsentBannerProps) => {
    const [dismissed, setDismissed] = useState(false);

    if (dismissed) return null;

    const text = getConfig(
        'VITE_COOKIE_CONSENT_TEXT',
        t`This site uses cookies for analytics purposes.`
    );

    const handleAccept = () => {
        onConsent(true);
        setDismissed(true);
    };

    const handleDecline = () => {
        onConsent(false);
        setDismissed(true);
    };

    return (
        <div className={classes.banner}>
            <button
                className={classes.closeButton}
                onClick={handleDecline}
                aria-label={t`Close`}
            >
                <IconX size={14}/>
            </button>
            <div className={classes.content}>
                <div className={classes.iconWrapper}>
                    <IconCookie size={20}/>
                </div>
                <p className={classes.text}>{text}</p>
            </div>
            <div className={classes.actions}>
                <button
                    className={classes.declineButton}
                    onClick={handleDecline}
                >
                    {t`Decline`}
                </button>
                <button
                    className={classes.acceptButton}
                    onClick={handleAccept}
                >
                    {t`Accept`}
                </button>
            </div>
        </div>
    );
};
