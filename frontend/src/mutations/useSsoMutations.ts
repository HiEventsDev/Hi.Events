import {useMutation, useQueryClient} from "@tanstack/react-query";
import {authClient} from "../api/auth.client.ts";
import {SsoCodeExchange, ssoClient, StartSsoRequest} from "../api/sso.client.ts";
import {GET_SSO_IDENTITIES_QUERY_KEY} from "../queries/useGetSsoIdentities.ts";
import {IdentityConfirmation, IdParam, SsoIntent, SsoProvider, SsoSignupRequest} from "../types.ts";
import {redirectToSso} from "../utilites/ssoFlow.ts";

interface StartSsoVariables {
    provider: SsoProvider;
    request: StartSsoRequest;
    partnerInviteToken?: string;
}

export const useStartSso = () => useMutation({
    mutationFn: ({provider, request}: StartSsoVariables) => ssoClient.start(provider.key, request),
    onSuccess: (response, {provider, request, partnerInviteToken}) => redirectToSso(response, {
        provider: provider.key,
        providerName: provider.name,
        intent: request.intent,
        returnPath: request.return_path,
        invitationToken: request.invitation_token,
        partnerInviteToken,
        timezone: request.timezone,
    }),
});

interface StartSelfServiceVariables {
    provider: SsoProvider;
    intent: Extract<SsoIntent, 'LINK' | 'REAUTH'>;
    returnPath: string;
    confirmation?: IdentityConfirmation;
}

export const useStartSelfServiceSso = () => useMutation({
    mutationFn: ({provider, intent, returnPath, confirmation}: StartSelfServiceVariables) => intent === 'LINK'
        ? ssoClient.startLink(provider.key, {...confirmation, return_path: returnPath})
        : ssoClient.startReauth(provider.key, returnPath),
    onSuccess: (response, {provider, intent, returnPath}) => redirectToSso(response, {
        provider: provider.key,
        providerName: provider.name,
        intent,
        returnPath,
    }),
});

export const useExchangeSsoCode = () => useMutation({
    mutationFn: ({request, authenticated}: { request: SsoCodeExchange, authenticated: boolean }) => authenticated
        ? ssoClient.exchangeAuthenticated(request)
        : ssoClient.exchange(request),
});

export const useCompleteSsoLogin = () => useMutation({
    mutationFn: (request: { login_ticket: string, binding: string, account_id: IdParam }) => ssoClient.complete(request),
});

export const useSendSsoSignupCode = () => useMutation({
    mutationFn: (request: { signup_ticket: string, binding: string, locale?: string }) => ssoClient.sendSignupCode(request),
});

export const useCompleteSsoSignup = () => useMutation({
    mutationFn: (request: SsoSignupRequest) => ssoClient.signup(request),
});

export const useDeleteSsoIdentity = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({identityId, confirmation}: { identityId: IdParam, confirmation: IdentityConfirmation }) => ssoClient.deleteIdentity(identityId, confirmation),
        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_SSO_IDENTITIES_QUERY_KEY]}),
    });
};

export const useJoinInvitation = () => useMutation({
    mutationFn: (token: string) => authClient.joinInvitation(token),
});

export const useSendPasswordSetupLink = () => useMutation({
    mutationFn: (email: string) => authClient.forgotPassword({email}),
});
