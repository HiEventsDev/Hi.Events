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
        t`We use cookies to help us understand how the site is used and to improve your experience.`
    );
    const privacyUrl = getConfig('VITE_PRIVACY_URL', 'https://hi.events/privacy-policy?utm_source=app-cookie-banner');

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
                <p className={classes.text}>
                    {text}
                    {privacyUrl && (
                        <>
                            {' '}
                            <a href={privacyUrl as string} target="_blank" rel="noopener noreferrer" className={classes.privacyLink}>
                                {t`Privacy Policy`}
                            </a>
                        </>
                    )}
                </p>
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
