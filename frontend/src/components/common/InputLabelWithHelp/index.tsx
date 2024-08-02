import {Popover} from "../Popover";
import {IconInfoCircle} from "@tabler/icons-react";
import classes from "./InputLabelWithHelp.module.scss";

interface InputLabelWithHelpProps {
    label: string;
    helpText: React.ReactNode;
}

export const InputLabelWithHelp = ({label, helpText}: InputLabelWithHelpProps) => {
    return (
        <div className={classes.labelWrapper}>
            <label>{label}</label>
            <div className={classes.helpIcon}>
                <Popover title={helpText}>
                    <IconInfoCircle size={14}/>
                </Popover>
            </div>
        </div>
    );
}
