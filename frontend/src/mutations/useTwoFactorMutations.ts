import {useMutation, useQueryClient} from "@tanstack/react-query";
import {twoFactorClient} from "../api/twoFactor.client.ts";
import {GET_TWO_FACTOR_STATUS_QUERY_KEY} from "../queries/useGetTwoFactorStatus.ts";
import {GET_ME_QUERY_KEY} from "../queries/useGetMe.ts";
import {DisableTwoFactorRequest, IdParam} from "../types.ts";

const useInvalidateTwoFactor = () => {
    const queryClient = useQueryClient();

    return () => Promise.all([
        queryClient.invalidateQueries({queryKey: [GET_TWO_FACTOR_STATUS_QUERY_KEY]}),
        queryClient.invalidateQueries({queryKey: [GET_ME_QUERY_KEY]}),
    ]);
};

export const useBeginTwoFactorSetup = () => {
    return useMutation({
        mutationFn: (password: string) => twoFactorClient.beginSetup(password).then(response => response.data),
    });
};

export const useConfirmTwoFactorSetup = () => {
    const invalidate = useInvalidateTwoFactor();

    return useMutation({
        mutationFn: (code: string) => twoFactorClient.confirmSetup(code).then(response => response.data.recovery_codes),
        onSuccess: invalidate,
    });
};

export const useRegenerateRecoveryCodes = () => {
    const invalidate = useInvalidateTwoFactor();

    return useMutation({
        mutationFn: (code: string) => twoFactorClient.regenerateRecoveryCodes(code).then(response => response.data.recovery_codes),
        onSuccess: invalidate,
    });
};

export const useDisableTwoFactor = () => {
    const invalidate = useInvalidateTwoFactor();

    return useMutation({
        mutationFn: (request: DisableTwoFactorRequest) => twoFactorClient.disable(request),
        onSuccess: invalidate,
    });
};

export const useRevokeTrustedDevices = () => {
    const invalidate = useInvalidateTwoFactor();

    return useMutation({
        mutationFn: () => twoFactorClient.revokeTrustedDevices(),
        onSuccess: invalidate,
    });
};

export const useRevokeTrustedDevice = () => {
    const invalidate = useInvalidateTwoFactor();

    return useMutation({
        mutationFn: (deviceId: IdParam) => twoFactorClient.revokeTrustedDevice(deviceId),
        onSuccess: invalidate,
    });
};
