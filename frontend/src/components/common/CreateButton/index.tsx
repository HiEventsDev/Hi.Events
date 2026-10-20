import {forwardRef} from "react";
import {Button, ButtonProps, createPolymorphicComponent} from "@mantine/core";
import {useAccess} from "../../../hooks/useAccess.ts";

const CreateButtonBase = forwardRef<HTMLButtonElement, ButtonProps>((props, ref) => {
    const {isReadOnly} = useAccess();

    return isReadOnly ? null : <Button ref={ref} {...props}/>;
});

export const CreateButton = createPolymorphicComponent<'button', ButtonProps>(CreateButtonBase);
