import {Loader} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconCopy, IconCheck} from "@tabler/icons-react";
import {useEffect, useState} from "react";
import classNames from "classnames";
import {SsoProvider} from "../../../types.ts";
import {getLastSsoProvider, isInAppBrowser} from "../../../utilites/ssoFlow.ts";
import {Callout} from "../Callout";
import {ProviderMark} from "./ProviderMark.tsx";
import classes from "./SsoButtons.module.scss";

export type SsoButtonVariant = 'signIn' | 'signUp';

interface SsoButtonsProps {
    providers: SsoProvider[];
    variant?: SsoButtonVariant;
    onStart: (provider: SsoProvider) => Promise<void>;
    disabled?: boolean;
}

const ssoButtonLabel = (provider: SsoProvider, variant: SsoButtonVariant = 'signIn'): string => {
    if (provider.type === 'MICROSOFT') {
        return t`Sign in with Microsoft`;
    }

    if (provider.type === 'GOOGLE') {
        return variant === 'signUp' ? t`Sign up with Google` : t`Continue with Google`;
    }

    return variant === 'signUp' ? t`Sign up with ${provider.name}` : t`Continue with ${provider.name}`;
};

const InAppBrowserNotice = () => {
    const [copyState, setCopyState] = useState<'idle' | 'copied' | 'failed'>('idle');

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(window.location.href);
            setCopyState('copied');
        } catch {
            setCopyState('failed');
        }
    };

    return (
        <Callout variant="warning" title={t`Open this page in your browser`}>
            {t`Google and Microsoft don't allow signing in from inside other apps. Open this page in Safari or Chrome to continue.`}
            <div className={classes.inAppActions}>
                {copyState === 'failed' ? (
                    <input className={classes.copyFallback} readOnly value={window.location.href} onFocus={(event) => event.currentTarget.select()} aria-label={t`Page address`}/>
                ) : (
                    <button type="button" className={classes.copyButton} onClick={copyLink}>
                        {copyState === 'copied' ? <IconCheck size={14}/> : <IconCopy size={14}/>}
                        {copyState === 'copied' ? t`Link copied` : t`Copy link`}
                    </button>
                )}
            </div>
        </Callout>
    );
};

export const SsoButtons = ({providers, variant = 'signIn', onStart, disabled = false}: SsoButtonsProps) => {
    const [pendingProvider, setPendingProvider] = useState<string | null>(null);
    const [lastUsedProvider, setLastUsedProvider] = useState<string | null>(null);
    const [inAppBrowser, setInAppBrowser] = useState(false);

    useEffect(() => {
        setLastUsedProvider(getLastSsoProvider());
        setInAppBrowser(isInAppBrowser());

        const resetOnReturn = (event: PageTransitionEvent) => {
            if (event.persisted) {
                setPendingProvider(null);
            }
        };

        window.addEventListener('pageshow', resetOnReturn);

        return () => window.removeEventListener('pageshow', resetOnReturn);
    }, []);

    if (providers.length === 0) {
        return null;
    }

    if (inAppBrowser) {
        return <InAppBrowserNotice/>;
    }

    const start = async (provider: SsoProvider) => {
        setPendingProvider(provider.key);

        try {
            await onStart(provider);
        } catch {
            setPendingProvider(null);
        }
    };

    return (
        <div className={classes.stack}>
            {providers.map(provider => {
                const isPending = pendingProvider === provider.key;
                const isLastUsed = providers.length > 1 && lastUsedProvider === provider.key;
                const lastUsedId = `sso-last-used-${provider.key}`;

                return (
                    <div key={provider.key} className={classes.item}>
                        <button
                            type="button"
                            className={classNames(classes.button, {
                                [classes.google]: provider.type === 'GOOGLE',
                                [classes.microsoft]: provider.type === 'MICROSOFT',
                            })}
                            onClick={() => start(provider)}
                            disabled={disabled || pendingProvider !== null}
                            aria-busy={isPending}
                            aria-describedby={isLastUsed ? lastUsedId : undefined}
                            data-testid={`sso-provider-button-${provider.key}`}
                        >
                            <span className={classes.mark}><ProviderMark provider={provider}/></span>
                            <span>{ssoButtonLabel(provider, variant)}</span>
                            {isPending && <span className={classes.spinner}><Loader size={16} color="gray"/></span>}
                        </button>
                        {isLastUsed && (
                            <span id={lastUsedId} className={classes.lastUsed}>{t`Last used`}</span>
                        )}
                    </div>
                );
            })}
        </div>
    );
};

export const SsoDivider = () => <div className={classes.divider} role="separator">{t`or`}</div>;
