import {useEffect, useState} from 'react';
import {t} from '@lingui/macro';
import {isConsentBannerEnabled, isEmbedded, openCookieSettings} from '../../../utilites/cookieConsent';
import classes from './CookieSettingsLink.module.scss';

export const CookieSettingsLink = () => {
    const [hidden, setHidden] = useState(false);

    useEffect(() => {
        setHidden(isEmbedded());
    }, []);

    if (hidden || !isConsentBannerEnabled()) return null;

    return (
        <button type="button" className={classes.link} onClick={openCookieSettings}>
            {t`Cookie settings`}
        </button>
    );
};
