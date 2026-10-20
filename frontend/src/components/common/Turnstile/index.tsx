import {useEffect, useRef, useState} from "react";
import {Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {getConfig} from "../../../utilites/config.ts";

const SCRIPT_ID = 'cf-turnstile-script';
const SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

interface TurnstileApi {
    render: (container: HTMLElement, options: Record<string, unknown>) => string;
    remove: (widgetId: string) => void;
    reset: (widgetId: string) => void;
}

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

interface TurnstileProps {
    onToken: (token: string) => void;
    resetKey?: number;
}

const loadScript = (): Promise<void> => new Promise((resolve, reject) => {
    if (window.turnstile) {
        resolve();
        return;
    }

    const existing = document.getElementById(SCRIPT_ID) as HTMLScriptElement | null;
    const script = existing ?? document.createElement('script');

    script.addEventListener('load', () => resolve());
    script.addEventListener('error', () => reject());

    if (!existing) {
        script.id = SCRIPT_ID;
        script.src = SCRIPT_SRC;
        script.async = true;
        document.head.appendChild(script);
    }
});

export const getTurnstileSiteKey = () => getConfig('VITE_TURNSTILE_SITE_KEY');

export const Turnstile = ({onToken, resetKey}: TurnstileProps) => {
    const containerRef = useRef<HTMLDivElement>(null);
    const widgetIdRef = useRef<string | null>(null);
    const [failed, setFailed] = useState(false);
    const siteKey = getTurnstileSiteKey();

    const fail = () => {
        setFailed(true);
        onToken('');
    };

    useEffect(() => {
        if (!siteKey || !containerRef.current) {
            return;
        }

        let cancelled = false;

        loadScript().then(() => {
            if (cancelled || !containerRef.current || !window.turnstile) {
                return;
            }

            widgetIdRef.current = window.turnstile.render(containerRef.current, {
                sitekey: siteKey,
                callback: (token: string) => {
                    setFailed(false);
                    onToken(token);
                },
                'expired-callback': () => onToken(''),
                'error-callback': fail,
            });
        }).catch(fail);

        return () => {
            cancelled = true;
            if (widgetIdRef.current && window.turnstile) {
                window.turnstile.remove(widgetIdRef.current);
                widgetIdRef.current = null;
            }
        };
    }, [siteKey]);

    useEffect(() => {
        if (resetKey && widgetIdRef.current && window.turnstile) {
            window.turnstile.reset(widgetIdRef.current);
            onToken('');
        }
    }, [resetKey]);

    if (!siteKey) {
        return null;
    }

    return (
        <div style={{marginBottom: 'var(--mantine-spacing-md)'}}>
            <div ref={containerRef}/>
            {failed && (
                <Text size="sm" c="red">
                    {t`The security check could not load. Turn off content or ad blockers for this page, or try another browser.`}
                </Text>
            )}
        </div>
    );
};
