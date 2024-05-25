import {useGetMe} from "../queries/useGetMe.ts";

export const useIsCurrentUserAdmin = () => {
    const {data: user, isFetched} = useGetMe();
    return isFetched && user?.role === 'ADMIN';
}