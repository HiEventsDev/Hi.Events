import {useGetMe} from "../queries/useGetMe.ts";
import {IdParam, Permission} from "../types.ts";

const toNumber = (id: IdParam | undefined | null) => Number(id);

export const useAccess = () => {
    const {data: me, isFetched} = useGetMe();
    const access = me?.access;
    const isRestricted = access?.scope === 'RESTRICTED';
    const permissions = access?.permissions ?? [];

    const can = (permission: Permission) => permissions.includes(permission);

    const canManageOrganizer = (organizerId: IdParam | undefined) =>
        !isRestricted || !!access?.organizer_ids.includes(toNumber(organizerId));

    return {
        isLoaded: isFetched && !!access,
        isRestricted,
        isReadOnly: isFetched && !!access && !can('EDIT'),
        can,
        canManageOrganizer,
        canCreateOrganizers: !isRestricted && can('EDIT'),
    };
};
