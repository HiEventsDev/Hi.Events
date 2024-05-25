import {useGetAccount} from "../queries/useGetAccount.ts";

export const useIsAccountVerified = () => {
    const {data: account, isFetched} = useGetAccount();
    return isFetched && account?.is_account_email_confirmed;
}