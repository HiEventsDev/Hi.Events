import {ReactNode} from "react";
import {useLocation} from "react-router";
import {StartSsoRequest} from "../../../api/sso.client.ts";
import {useStartSso} from "../../../mutations/useSsoMutations.ts";
import {useGetSsoProviders} from "../../../queries/useGetSsoProviders.ts";
import {SsoProvider} from "../../../types.ts";
import {ssoError} from "../../../utilites/ssoErrors.ts";
import {SsoButtons, SsoButtonVariant, SsoDivider} from "./index.tsx";

export const useSsoOptions = () => {
    const location = useLocation();
    const {data: sso} = useGetSsoProviders();
    const providers = sso?.providers ?? [];
    const passwordLoginEnabled = sso?.password_login_enabled ?? true;
    const buttonsVisible = (sso?.buttons_visible ?? false) || !passwordLoginEnabled || new URLSearchParams(location.search).has('sso');

    return {
        providers,
        passwordLoginEnabled,
        registrationEnabled: sso?.registration_enabled ?? true,
        enterpriseSsoEnabled: sso?.enterprise_sso_enabled ?? false,
        showSso: providers.length > 0 && buttonsVisible,
    };
};

interface SsoSectionProps {
    request: StartSsoRequest;
    onError: (message: string) => void;
    variant?: SsoButtonVariant;
    partnerInviteToken?: string;
    disabled?: boolean;
    hidden?: boolean;
    trailing?: ReactNode;
}

export const SsoSection = ({request, onError, variant, partnerInviteToken, disabled, hidden = false, trailing}: SsoSectionProps) => {
    const {providers, passwordLoginEnabled, showSso} = useSsoOptions();
    const startSso = useStartSso();

    if (hidden || (!showSso && !trailing)) {
        return null;
    }

    const start = async (provider: SsoProvider) => {
        try {
            await startSso.mutateAsync({provider, request, partnerInviteToken});
        } catch (error) {
            onError(ssoError(error).message);
            throw error;
        }
    };

    return (
        <>
            {showSso && <SsoButtons providers={providers} variant={variant} onStart={start} disabled={disabled}/>}
            {trailing}
            {passwordLoginEnabled && <SsoDivider/>}
        </>
    );
};
