import {Fieldset as MantineFieldset, FieldsetProps as MantineFieldsetProps} from "@mantine/core";
import classNames from "./Fieldset.module.scss";

export interface FieldsetProps extends MantineFieldsetProps {
    children: React.ReactNode;
}

export const Fieldset = (props: FieldsetProps) => {
    return (
        <MantineFieldset {...props}
                         variant={'filled'}
                         legend={<span className={classNames.legend}>{props.legend}</span>}

        >
            {props.children}
        </MantineFieldset>
    );
}