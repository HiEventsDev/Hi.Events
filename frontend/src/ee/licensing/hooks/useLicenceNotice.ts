import {useGetMe} from "../../../queries/useGetMe.ts";
import {useAccess} from "../../../hooks/useAccess.ts";
import {useGetAccount} from "../../../queries/useGetAccount.ts";
import {LicenceNotice, licenceNoticeFor} from "../licenceNotice.ts";

export const useLicenceNotice = (): LicenceNotice | null => {
    const {data: me} = useGetMe();
    const {data: account} = useGetAccount();
    const {can} = useAccess();

    const canSeeLicence = account?.is_saas_mode_enabled
        ? me?.role === 'SUPERADMIN'
        : can('MANAGE_ACCOUNT');

    return me?.licence && canSeeLicence ? licenceNoticeFor(me.licence) : null;
};
