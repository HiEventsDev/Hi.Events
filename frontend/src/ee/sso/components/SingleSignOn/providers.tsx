import {t} from "@lingui/macro";
import {IconKey} from "@tabler/icons-react";
import {AccountSsoConnectionType} from "../../../../types.ts";
import {ProviderMark} from "../../../../components/common/SsoButtons/ProviderMark.tsx";

export const connectionTypes: AccountSsoConnectionType[] = ['OKTA', 'ENTRA', 'GOOGLE_WORKSPACE', 'OIDC'];

export const connectionTypeLabel = (type: AccountSsoConnectionType): string => {
    switch (type) {
        case 'OKTA':
            return 'Okta';
        case 'ENTRA':
            return 'Microsoft Entra ID';
        case 'GOOGLE_WORKSPACE':
            return 'Google Workspace';
        default:
            return t`Other OIDC provider`;
    }
};

export const connectionProviderName = (type: AccountSsoConnectionType): string => type === 'OIDC'
    ? t`your identity provider`
    : connectionTypeLabel(type);

export const connectionTypeDescription = (type: AccountSsoConnectionType): string => {
    switch (type) {
        case 'OKTA':
            return t`Okta Workforce Identity`;
        case 'ENTRA':
            return t`Formerly Azure Active Directory`;
        case 'GOOGLE_WORKSPACE':
            return t`Sign in with your company Google accounts`;
        default:
            return t`Keycloak, Authentik, Zitadel, OneLogin and others`;
    }
};

export const setupInstructions = (type: AccountSsoConnectionType): string[] => {
    switch (type) {
        case 'OKTA':
            return [
                t`In the Okta Admin Console, go to Applications and choose Create App Integration.`,
                t`Pick OIDC - OpenID Connect, then Web Application.`,
                t`Add the sign-in redirect URI below, and assign the people or groups who should have access.`,
                t`Copy your Okta domain, the client ID and the client secret into the fields below.`,
            ];
        case 'ENTRA':
            return [
                t`In the Microsoft Entra admin center, go to App registrations and choose New registration.`,
                t`Allow accounts in this organizational directory only, and add the redirect URI below as a Web redirect URI.`,
                t`Under Certificates & secrets, create a client secret.`,
                t`Under Token configuration, add the optional claims email and xms_edov to the ID token.`,
                t`Copy the directory (tenant) ID, the application (client) ID and the secret value into the fields below.`,
            ];
        case 'GOOGLE_WORKSPACE':
            return [
                t`In the Google Cloud console, open a project that belongs to your Workspace organization.`,
                t`Set the OAuth consent screen's user type to Internal.`,
                t`Under Credentials, create an OAuth client ID for a web application and add the redirect URI below.`,
                t`Copy the client ID and client secret into the fields below, with your Workspace domain.`,
            ];
        default:
            return [
                t`In your identity provider, create a confidential OpenID Connect client that uses the authorization code flow.`,
                t`Add the redirect URI below and allow the openid, email and profile scopes.`,
                t`Make sure ID tokens include the email and email_verified claims.`,
                t`Copy the issuer URL, the client ID and the client secret into the fields below.`,
            ];
    }
};

const OktaMark = ({size}: { size: number }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <circle cx="12" cy="12" r="8.5" fill="none" stroke="#007DC1" strokeWidth="5"/>
    </svg>
);

export const ConnectionMark = ({type, size = 20}: { type: AccountSsoConnectionType, size?: number }) => {
    switch (type) {
        case 'OKTA':
            return <OktaMark size={size}/>;
        case 'ENTRA':
            return <ProviderMark provider={{key: 'entra', name: 'Microsoft', type: 'MICROSOFT', logo_url: null}} size={size}/>;
        case 'GOOGLE_WORKSPACE':
            return <ProviderMark provider={{key: 'workspace', name: 'Google', type: 'GOOGLE', logo_url: null}} size={size}/>;
        default:
            return <IconKey size={size} stroke={1.75} aria-hidden="true"/>;
    }
};
