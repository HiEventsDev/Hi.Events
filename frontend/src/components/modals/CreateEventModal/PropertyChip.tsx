import {ComponentPropsWithoutRef, forwardRef, ReactNode} from "react";
import {UnstyledButton} from "@mantine/core";
import {IconX} from "@tabler/icons-react";
import classNames from "classnames";
import classes from "./CreateEventModal.module.scss";

interface PropertyChipProps extends ComponentPropsWithoutRef<'button'> {
    icon?: ReactNode;
    empty?: boolean;
    invalid?: boolean;
    onClear?: () => void;
    clearLabel?: string;
    clearTestId?: string;
}

export const PropertyChip = forwardRef<HTMLButtonElement, PropertyChipProps>((
    {icon, empty, invalid, onClear, clearLabel, clearTestId, className, children, ...buttonProps},
    ref,
) => (
    <span className={classes.chipGroup}>
        <UnstyledButton
            ref={ref}
            type="button"
            className={classNames(
                classes.chip,
                empty && classes.chipEmpty,
                invalid && classes.chipInvalid,
                onClear && classes.chipWithClear,
                className,
            )}
            {...buttonProps}
        >
            {icon}
            <span className={classes.chipLabel}>{children}</span>
        </UnstyledButton>
        {onClear && (
            <UnstyledButton
                type="button"
                className={classes.chipClear}
                onClick={onClear}
                aria-label={clearLabel}
                data-testid={clearTestId}
            >
                <IconX size={12}/>
            </UnstyledButton>
        )}
    </span>
));
