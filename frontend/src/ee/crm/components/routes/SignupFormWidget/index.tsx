import {t} from "@lingui/macro";
import {useParams} from "react-router";
import {useWidgetAppearance, useWidgetRuntime} from "../../../../../components/widget/useWidgetRuntime.ts";
import {WidgetFrame} from "../../../../../components/widget/WidgetFrame";
import {WidgetMessage} from "../../../../../components/widget/WidgetMessage";
import {getParentOrigin} from "../../../../../utilites/iframeResize.ts";
import {useGetSignupFormPublic} from "../../../queries/useCrmSignupFormQueries.ts";
import {SignupFormCard} from "../../SignupFormCard";

const notifyParent = () => {
    if (window.parent !== window) {
        window.parent.postMessage({type: 'hievents:subscribed'}, getParentOrigin() ?? '*');
    }
};

const SignupFormWidget = () => {
    const {publicId} = useParams();
    const {config, notFound} = useWidgetRuntime();
    const appearance = useWidgetAppearance(config);
    const formQuery = useGetSignupFormPublic(publicId, !notFound);
    const form = formQuery.data;

    return (
        <WidgetFrame style={appearance.style} mode={appearance.mode}>
            {form
                ? <SignupFormCard form={form} publicId={form.public_id} onSubmitted={notifyParent}/>
                : (notFound || formQuery.isError) && <WidgetMessage title={t`This signup form isn't available.`}/>}
        </WidgetFrame>
    );
};

export default SignupFormWidget;
