import {useMutation, useQueryClient} from "@tanstack/react-query";
import {accountSsoClient} from "../api/accountSso.client.ts";
import {GET_ACCOUNT_SSO_QUERY_KEY} from "../queries/useGetAccountSso.ts";
import {GET_SSO_ENFORCEMENT_PREVIEW_QUERY_KEY} from "../queries/useGetSsoEnforcementPreview.ts";
import {GET_USERS_QUERY_KEY} from "../../../queries/useGetUsers.ts";
import {AccountSsoConnection, AccountSsoConnectionRequest, IdParam} from "../../../types.ts";

const useInvalidateAccountSso = () => {
    const queryClient = useQueryClient();

    return () => Promise.all([
        queryClient.invalidateQueries({queryKey: [GET_ACCOUNT_SSO_QUERY_KEY]}),
        queryClient.invalidateQueries({queryKey: [GET_SSO_ENFORCEMENT_PREVIEW_QUERY_KEY]}),
        queryClient.invalidateQueries({queryKey: [GET_USERS_QUERY_KEY]}),
    ]);
};

export const useSaveSsoConnection = () => {
    const invalidate = useInvalidateAccountSso();

    return useMutation({
        mutationFn: ({accountId, request, isNew}: { accountId: IdParam, request: AccountSsoConnectionRequest, isNew: boolean }) => isNew
            ? accountSsoClient.createConnection(accountId, request)
            : accountSsoClient.updateConnection(accountId, request),
        onSuccess: invalidate,
    });
};

export const useCheckSsoConnection = () => useMutation({
    mutationFn: ({accountId, request}: { accountId: IdParam, request: AccountSsoConnectionRequest }) => accountSsoClient.checkConnection(accountId, request),
});

export const useSetSsoConnectionStatus = () => {
    const invalidate = useInvalidateAccountSso();

    return useMutation({
        mutationFn: ({accountId, status}: { accountId: IdParam, status: AccountSsoConnection['status'] }) => accountSsoClient.setConnectionStatus(accountId, status),
        onSuccess: invalidate,
    });
};

export const useDeleteSsoConnection = () => {
    const invalidate = useInvalidateAccountSso();

    return useMutation({
        mutationFn: (accountId: IdParam) => accountSsoClient.deleteConnection(accountId),
        onSuccess: invalidate,
    });
};

export const useStartSsoTest = () => useMutation({
    mutationFn: ({accountId, returnPath}: { accountId: IdParam, returnPath: string }) => accountSsoClient.startTest(accountId, returnPath),
});

export const useAddSsoDomain = () => {
    const invalidate = useInvalidateAccountSso();

    return useMutation({
        mutationFn: ({accountId, domain}: { accountId: IdParam, domain: string }) => accountSsoClient.addDomain(accountId, domain),
        onSuccess: invalidate,
    });
};

export const useVerifySsoDomain = () => {
    const invalidate = useInvalidateAccountSso();

    return useMutation({
        mutationFn: ({accountId, domainId}: { accountId: IdParam, domainId: IdParam }) => accountSsoClient.verifyDomain(accountId, domainId),
        onSettled: invalidate,
    });
};

export const useDeleteSsoDomain = () => {
    const invalidate = useInvalidateAccountSso();

    return useMutation({
        mutationFn: ({accountId, domainId}: { accountId: IdParam, domainId: IdParam }) => accountSsoClient.deleteDomain(accountId, domainId),
        onSuccess: invalidate,
    });
};

export const useUpdateSsoEnforcement = () => {
    const invalidate = useInvalidateAccountSso();

    return useMutation({
        mutationFn: ({accountId, enabled, exemptAccountUserIds}: { accountId: IdParam, enabled: boolean, exemptAccountUserIds?: IdParam[] }) =>
            accountSsoClient.updateEnforcement(accountId, enabled, exemptAccountUserIds),
        onSuccess: invalidate,
    });
};

export const useSetSsoMemberEnforced = () => {
    const invalidate = useInvalidateAccountSso();

    return useMutation({
        mutationFn: ({accountId, accountUserId, enforced}: { accountId: IdParam, accountUserId: IdParam, enforced: boolean }) =>
            accountSsoClient.setMemberEnforced(accountId, accountUserId, enforced),
        onSuccess: invalidate,
    });
};
