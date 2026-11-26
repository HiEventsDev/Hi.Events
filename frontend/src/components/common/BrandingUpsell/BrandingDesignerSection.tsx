import {t} from "@lingui/macro";
import {IconEyeOff} from "@tabler/icons-react";
import {DesignerSection} from "../DesignerShell";
import {useBrandingSubscription} from "../../../hooks/useBrandingSubscription.ts";
import {BrandingSwitch} from "./BrandingSwitch.tsx";

export const BrandingDesignerSection = () => {
    const {isAvailable} = useBrandingSubscription();

    if (!isAvailable) {
        return null;
    }

    return (
        <DesignerSection icon={<IconEyeOff/>} title={t`Branding`}>
            <BrandingSwitch/>
        </DesignerSection>
    );
};
