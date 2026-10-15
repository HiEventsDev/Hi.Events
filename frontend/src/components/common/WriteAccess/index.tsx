import {ReactNode} from "react";
import {useAccess} from "../../../hooks/useAccess.ts";

export const WriteAccess = ({children}: { children: ReactNode }) => {
    const {isReadOnly} = useAccess();

    return isReadOnly ? null : <>{children}</>;
};
