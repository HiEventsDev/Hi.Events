import {Button, Loader} from "@mantine/core";
import {t} from "@lingui/macro";
import {ComponentProps, useEffect, useMemo, useState} from "react";
import type {ApiReferenceReact} from "@scalar/api-reference-react";
import {useGetApiReference} from "../../../../queries/useGetApiReference.ts";
import {getConfig} from "../../../../utilites/config.ts";
import {useApiBaseUrl} from "../ApiKeys/useApiBaseUrl.ts";
import {SCALAR_THEME_CSS} from "./scalarTheme.ts";
import classes from "./ApiReference.module.scss";

type ReferenceComponent = typeof ApiReferenceReact;

type Configuration = ComponentProps<ReferenceComponent>['configuration'];

const ApiReference = () => {
    const {data: spec, isError, refetch} = useGetApiReference();
    const apiBaseUrl = useApiBaseUrl();
    const [Reference, setReference] = useState<ReferenceComponent | null>(null);
    const [loadFailed, setLoadFailed] = useState(false);
    const [container, setContainer] = useState<HTMLDivElement | null>(null);

    useEffect(() => {
        Promise.all([
            import("@scalar/api-reference-react"),
            import("@scalar/api-reference-react/style.css"),
        ])
            .then(([module]) => setReference(() => module.ApiReferenceReact))
            .catch(() => setLoadFailed(true));
    }, []);

    useEffect(() => {
        if (!container) {
            return;
        }

        const demoteNestedMain = () => container
            .querySelectorAll('main:not([role])')
            .forEach(main => main.setAttribute('role', 'region'));

        demoteNestedMain();
        const observer = new MutationObserver(demoteNestedMain);
        observer.observe(container, {childList: true, subtree: true});

        return () => observer.disconnect();
    }, [container]);

    const configuration = useMemo<Configuration | null>(() => {
        if (!spec) {
            return null;
        }

        const info = (spec.info ?? {}) as Record<string, unknown>;

        return {
            content: {...spec, info: {...info, title: t`${getConfig('VITE_APP_NAME', 'Hi.Events')} API`}},
            servers: [{url: apiBaseUrl}],
            theme: 'none',
            customCss: SCALAR_THEME_CSS,
            layout: 'modern',
            forceDarkModeState: 'light',
            hideDarkModeToggle: true,
            withDefaultFonts: false,
            telemetry: false,
            showDeveloperTools: 'never',
            documentDownloadType: 'json',
            hideModels: false,
            persistAuth: false,
            authentication: {preferredSecurityScheme: 'http'},
            agent: {disabled: true},
            mcp: {disabled: true},
        };
    }, [spec, apiBaseUrl]);

    if (isError || loadFailed) {
        return (
            <div className={classes.state}>
                <p>{t`Couldn't load the API reference.`}</p>
                <Button
                    variant="default"
                    size="xs"
                    onClick={() => (loadFailed ? window.location.reload() : refetch())}
                >
                    {t`Try again`}
                </Button>
            </div>
        );
    }

    if (!configuration || !Reference) {
        return (
            <div className={classes.state} aria-busy>
                <Loader size="sm"/>
                <p>{t`Loading the API reference…`}</p>
            </div>
        );
    }

    return (
        <div ref={setContainer} className={classes.reference} data-testid="api-reference">
            <Reference configuration={configuration}/>
        </div>
    );
};

export default ApiReference;
