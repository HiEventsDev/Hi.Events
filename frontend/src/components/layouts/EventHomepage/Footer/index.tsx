import classes from './Footer.module.scss';
import {PoweredByFooter} from "../../../common/PoweredByFooter";

export const Footer = () => {
    return (
        /**
         * (c) Hi.Events Ltd 2024
         *
         * PLEASE NOTE:
         *
         * Hi.Events is licensed under the GNU Affero General Public License (AGPL) version 3.
         *
         * You can find the full license text at: https://github.com/HiEventsDev/hi.events/blob/main/LICENSE
         *
         * In accordance with Section 7(b) of the AGPL, we ask that you retain the "Powered by Hi.Events" notice.
         *
         * If you wish to remove this notice, a commercial license is available at: https://hi.events/licensing
         */
        <footer className={classes.footer}>
            <PoweredByFooter/>
        </footer>
    )
}
