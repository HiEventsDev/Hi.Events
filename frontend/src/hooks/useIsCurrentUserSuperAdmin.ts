import {useGetMe} from "../queries/useGetMe.ts";

export const useIsCurrentUserSuperAdmin = () => {
    const {data: user, isFetched} = useGetMe();
    return isFetched && user?.role === 'SUPERADMIN';
}
