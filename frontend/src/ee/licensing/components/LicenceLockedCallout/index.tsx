import {t} from "@lingui/macro";
import {Callout} from "../../../../components/common/Callout";

export const LicenceLockedCallout = () => (
    <div data-testid="licence-locked-callout">
        <Callout variant="warning" title={t`Setup is locked`}>
            {t`This feature needs a Hi.Events Enterprise licence. Until a licence key is added, nothing new can be set up and existing setup can't be changed, but what is already set up can still be viewed, switched off or removed.`}
        </Callout>
    </div>
);
