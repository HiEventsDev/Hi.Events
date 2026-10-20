import {useEffect, useState} from 'react';
import {t} from '@lingui/macro';
import {Switch} from '@mantine/core';
import {IconCookie} from '@tabler/icons-react';
import classNames from 'classnames';
import {getConfig} from '../../../utilites/config';
import {
    ALL_DENIED,
    ALL_GRANTED,
    ConsentCategory,
    ConsentPreferences,
    OPEN_SETTINGS_EVENT,
    isEmbedded,
    readConsent,
    writeConsent,
} from '../../../utilites/cookieConsent';
import classes from './CookieConsentBanner.module.scss';

const revokesAnyCategory = (previous: ConsentPreferences | null, next: ConsentPreferences): boolean =>
    previous !== null && (Object.keys(next) as ConsentCategory[]).some((category) => previous[category] && !next[category]);

export const CookieConsentBanner = () => {
    const [visible, setVisible] = useState(false);
    const [expanded, setExpanded] = useState(false);
    const [stored, setStored] = useState<ConsentPreferences | null>(null);
    const [draft, setDraft] = useState<ConsentPreferences>(ALL_DENIED);

    useEffect(() => {
        if (isEmbedded()) return;

        const current = readConsent();
        setStored(current);
        setVisible(current === null);

        const openSettings = () => {
            const latest = readConsent();
            setStored(latest);
            setDraft(latest ?? ALL_DENIED);
            setExpanded(true);
            setVisible(true);
        };
        window.addEventListener(OPEN_SETTINGS_EVENT, openSettings);
        return () => window.removeEventListener(OPEN_SETTINGS_EVENT, openSettings);
    }, []);

    if (!visible) return null;

    const text = getConfig(
        'VITE_COOKIE_CONSENT_TEXT',
        t`We use cookies to help us understand how the site is used and to improve your experience.`
    );
    const privacyUrl = getConfig('VITE_PRIVACY_URL', 'https://hi.events/privacy-policy?utm_source=app-cookie-banner');

    const save = (next: ConsentPreferences) => {
        writeConsent(next);
        if (revokesAnyCategory(stored, next)) {
            window.location.reload();
            return;
        }
        setStored(next);
        setVisible(false);
        setExpanded(false);
    };

    const optionalCategories: { key: ConsentCategory; label: string; description: string }[] = [
        {key: 'analytics', label: t`Analytics`, description: t`Helps us understand how the site is used.`},
        {key: 'advertising', label: t`Advertising`, description: t`Used to measure and personalise ads.`},
    ];

    return (
        <div className={classNames(classes.banner, expanded && classes.expanded)} role="dialog" aria-label={t`Cookie settings`}>
            <div className={classes.content}>
                <div className={classes.iconWrapper}>
                    <IconCookie size={20}/>
                </div>
                <p className={classes.text}>
                    {text}
                    {privacyUrl && (
                        <>
                            {' '}
                            <a href={privacyUrl} target="_blank" rel="noopener noreferrer" className={classes.privacyLink}>
                                {t`Privacy Policy`}
                            </a>
                        </>
                    )}
                </p>
            </div>

            {expanded && (
                <div className={classes.categories}>
                    <div className={classes.category}>
                        <div className={classes.categoryText}>
                            <span className={classes.categoryLabel}>{t`Essential`}</span>
                            <span className={classes.categoryDescription}>
                                {t`Required for the site to work, such as keeping you signed in.`}
                            </span>
                        </div>
                        <Switch size="sm" checked disabled aria-label={t`Essential`}/>
                    </div>
                    {optionalCategories.map(({key, label, description}) => (
                        <div className={classes.category} key={key}>
                            <div className={classes.categoryText}>
                                <span className={classes.categoryLabel}>{label}</span>
                                <span className={classes.categoryDescription}>{description}</span>
                            </div>
                            <Switch
                                size="sm"
                                checked={draft[key]}
                                aria-label={label}
                                onChange={(event) => setDraft({...draft, [key]: event.currentTarget.checked})}
                            />
                        </div>
                    ))}
                </div>
            )}

            <div className={classes.actions}>
                {expanded ? (
                    <button
                        type="button"
                        className={classes.primaryButton}
                        onClick={() => save(draft)}
                        data-testid="cookie-consent-save"
                    >
                        {t`Save choices`}
                    </button>
                ) : (
                    <>
                        <button
                            type="button"
                            className={classes.ghostButton}
                            onClick={() => save(ALL_DENIED)}
                            data-testid="cookie-consent-reject-all"
                        >
                            {t`Reject all`}
                        </button>
                        <button
                            type="button"
                            className={classes.ghostButton}
                            onClick={() => setExpanded(true)}
                            data-testid="cookie-consent-more-choices"
                        >
                            {t`More choices`}
                        </button>
                    </>
                )}
                <button
                    type="button"
                    className={classes.primaryButton}
                    onClick={() => save(ALL_GRANTED)}
                    data-testid="cookie-consent-accept-all"
                >
                    {t`Accept all`}
                </button>
            </div>
        </div>
    );
};
