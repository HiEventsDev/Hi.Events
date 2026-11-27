import {useMutation} from "@tanstack/react-query";
import {selfServiceClient} from "../api/self-service.client.ts";
import {SelfServiceLink} from "../utilites/selfServiceSession.ts";

export const useRequestSelfServiceCode = () => useMutation({
    mutationFn: (link: SelfServiceLink) => selfServiceClient.requestVerification(link),
});

export const useConfirmSelfServiceCode = () => useMutation({
    mutationFn: ({link, code}: { link: SelfServiceLink; code: string }) => selfServiceClient.confirmVerification(link, code),
});

export const useStartLookupSession = () => useMutation({
    mutationFn: (lookupToken: string) => selfServiceClient.startSessionFromLookup(lookupToken),
});
