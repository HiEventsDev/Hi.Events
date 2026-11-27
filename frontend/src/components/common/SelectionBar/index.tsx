import {ComponentPropsWithoutRef, ReactNode} from "react";
import {plural, t} from "@lingui/macro";
import {ActionIcon} from "@mantine/core";
import {IconX} from "@tabler/icons-react";
import classes from "./SelectionBar.module.scss";

type FrameProps = ComponentPropsWithoutRef<'div'> & Record<`data-${string}`, string | undefined>;

export const SelectionBarFrame = ({children, ...props}: FrameProps) => (
    <div className={classes.dock}>
        <div className={classes.bar} {...props}>{children}</div>
    </div>
);

interface SelectionBarProps {
    count: number;
    label: string;
    onClear: () => void;
    children: ReactNode;
    dataTestId?: string;
    countTestId?: string;
    clearTestId?: string;
}

export const SelectionBar = ({count, label, onClear, children, dataTestId, countTestId, clearTestId}: SelectionBarProps) => (
    <SelectionBarFrame role="toolbar" aria-label={label} data-testid={dataTestId}>
        <span className={classes.count} data-testid={countTestId}>{plural(count, {one: '# selected', other: '# selected'})}</span>
        <span className={classes.divider}/>
        <div className={classes.actions}>{children}</div>
        <span className={classes.divider}/>
        <ActionIcon variant="subtle" color="gray" aria-label={t`Clear selection`} onClick={onClear} data-testid={clearTestId}>
            <IconX size={16}/>
        </ActionIcon>
    </SelectionBarFrame>
);
