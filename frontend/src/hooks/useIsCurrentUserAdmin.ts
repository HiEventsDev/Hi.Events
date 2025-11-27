import {useGetMe} from "../queries/useGetMe.ts";

export const useIsCurrentUserAdmin = () => {
    const {data: user, isFetched} = useGetMe();
    return isFetched && (user?.role === 'ADMIN' || user?.role === 'SUPERADMIN');
}

export const useIsCurrentUserSuperAdmin = () => {
    const {data: user, isFetched} = useGetMe();
    return isFetched && user?.role === 'SUPERADMIN';
}
