import classes from './Footer.module.scss';
import {PoweredByFooter} from "../../../common/PoweredByFooter";

export const Footer = () => {
    return (
        <footer className={classes.footer}>
            <PoweredByFooter/>
        </footer>
    )
}