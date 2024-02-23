import {FC, ReactNode} from 'react';
import {useWindowWidth} from "../../../hooks/useWindowWidth.ts";

type Props = {
    children: ReactNode;
}

const breakpoints = {
    xs: 320,
    sm: 576,
    md: 768,
    lg: 992,
    xl: 1200,
    xxl: 1440
};

const ShowForDesktop: FC<Props> = ({ children }) => {
    const width = useWindowWidth();
    return width > breakpoints.md ? <>{children}</> : null;
}

const ShowForMobile: FC<Props> = ({ children }) => {
    const width = useWindowWidth();
    return width <= breakpoints.md ? <>{children}</> : null;
}

export { ShowForDesktop, ShowForMobile};
