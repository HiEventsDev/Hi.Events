import {SeoSettings} from "./Sections/SeoSettings";
import ImageAssetSettings from "./Sections/ImageAssetSettings";
import BasicSettings from "./Sections/BasicSettings";

const Settings = () => {
    return (
        <>
            <BasicSettings/>
            <ImageAssetSettings/>
            <SeoSettings/>
        </>
    );
}

export default Settings;
