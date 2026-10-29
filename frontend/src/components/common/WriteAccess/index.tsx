import {ReactNode} from "react";
import {t} from "@lingui/macro";
import {useAccess} from "../../../hooks/useAccess.ts";
import {Permission} from "../../../types.ts";
import {NoResultsSplash} from "../NoResultsSplash";

export const WriteAccess = ({children}: { children: ReactNode }) => {
    const {isReadOnly} = useAccess();

    return isReadOnly ? null : <>{children}</>;
};

interface RequirePermissionProps {
    permission: Permission;
    imageHref?: string;
    children: ReactNode;
}

export const RequirePermission = ({permission, imageHref, children}: RequirePermissionProps) => {
    const {isLoaded, can} = useAccess();

    if (!isLoaded) {
        return null;
    }

    if (!can(permission)) {
        return (
            <NoResultsSplash
                heading={t`You don't have access to this page`}
                subHeading={<p>{t`Ask an account admin to update your permissions.`}</p>}
                imageHref={imageHref}
            />
        );
    }

    return <>{children}</>;
};
