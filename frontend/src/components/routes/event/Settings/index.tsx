import {PageBody} from "../../../common/PageBody";
import {EventDetailsForm} from "./Sections/EventDetailsForm";
import {LocationSettings} from "./Sections/LocationSettings";
import {HomepageAndCheckoutSettings} from "./Sections/HomepageAndCheckoutSettings";
import {EmailSettings} from "./Sections/EmailSettings";
import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {SeoSettings} from "./Sections/SeoSettings";
import {MiscSettings} from "./Sections/MiscSettings";

export const Settings = () => {
    return (
        <PageBody isFluid={false}>
            <PageTitle>
                {t`Settings`}
            </PageTitle>

            <EventDetailsForm/>
            <LocationSettings/>
            <HomepageAndCheckoutSettings/>
            <SeoSettings/>
            <EmailSettings/>
            <MiscSettings/>
        </PageBody>
    );
};

export default Settings;