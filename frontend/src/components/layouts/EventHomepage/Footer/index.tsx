import classes from './Footer.module.scss';
import {PoweredByFooter} from "../../../common/PoweredByFooter";

export const Footer = () => {
    return (
        /**
         * PLEASE NOTE:
         *
         * Under the terms of the license, you are not permitted to remove or obscure the powered by footer unless you have a white-label
         * or commercial license.
         * @see https://github.com/HiEventsDev/hi.events/blob/main/LICENCE#L13
         **
         * You can purchase a license at https://hi.events/licensing
         */
        <footer className={classes.footer}>
            <PoweredByFooter/>
        </footer>
    )
}