import {t} from "@lingui/macro";
import {Callout} from "../../../../components/common/Callout";

export const LicenceLockedCallout = () => (
    <div data-testid="licence-locked-callout">
        <Callout variant="warning" title={t`Setup is locked`}>
            {t`This feature needs a Hi.Events Enterprise licence. What is already set up keeps working and can still be viewed or removed, but it can't be changed until a licence key is added.`}
        </Callout>
    </div>
);
