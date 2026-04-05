import {Modal} from "../../common/Modal";
import {GenericModalProps} from "../../../types.ts";
import classes from "./AboutModal.module.scss";
import {t} from "@lingui/macro";

declare const __APP_VERSION__: string;

export const AboutModal = ({onClose}: GenericModalProps) => {
    return (
        <Modal onClose={onClose} opened>
            <div className={classes.aboutContainer}>
                <iframe src={'https://hi.' +
                    'events/about-embedded'}
                        className={classes.aboutIframe}
                        title="About"
                        allowFullScreen
                />
                <div className={classes.versionInfo}>
                    {t`Version`}: {__APP_VERSION__}
                </div>
            </div>
        </Modal>
    );
}
