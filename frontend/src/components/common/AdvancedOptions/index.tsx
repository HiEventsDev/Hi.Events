import {Collapse} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconChevronRight} from "@tabler/icons-react";
import {ReactNode} from "react";
import classes from "./AdvancedOptions.module.scss";

interface AdvancedOptionsProps {
    opened: boolean;
    onToggle: () => void;
    dataTestId?: string;
    children: ReactNode;
}

export const AdvancedOptions = ({opened, onToggle, dataTestId, children}: AdvancedOptionsProps) => {
    return (
        <>
            <button
                type="button"
                className={classes.advancedToggle}
                onClick={onToggle}
                aria-expanded={opened}
                data-testid={dataTestId}
            >
                <IconChevronRight
                    size={14}
                    className={`${classes.chevron} ${opened ? classes.chevronOpen : ""}`}
                />
                {opened ? t`Hide advanced options` : t`Show advanced options`}
            </button>

            <Collapse expanded={opened}>
                {children}
            </Collapse>
        </>
    );
}
