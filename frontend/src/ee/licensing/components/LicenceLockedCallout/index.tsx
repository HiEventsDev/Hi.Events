import {t} from "@lingui/macro";
import {Callout} from "../../../../components/common/Callout";
import {useGetMe} from "../../../../queries/useGetMe.ts";

export const LicenceLockedCallout = () => {
    const {data: me} = useGetMe();
    const status = me?.licence?.status;
    const hasLicence = status === 'ACTIVE' || status === 'GRACE';

    return (
        <div data-testid="licence-locked-callout">
            <Callout variant="warning" title={t`Setup is locked`}>
                {hasLicence
                    ? t`Your Hi.Events licence doesn't include this feature. Until it does, nothing new can be set up and existing setup can't be changed, but what is already set up can still be viewed, switched off or removed.`
                    : t`This feature needs a Hi.Events Enterprise licence. Until a licence key is added, nothing new can be set up and existing setup can't be changed, but what is already set up can still be viewed, switched off or removed.`}
            </Callout>
        </div>
    );
};
