import React, { ReactNode } from 'react';
import { LoadingMask } from "../LoadingMask";

type LoadingContainerProps = React.HTMLProps<HTMLDivElement> & {
    children: ReactNode;
};

export const LoadingContainer: React.FC<LoadingContainerProps> = ({children, ...props}) => {
    return (
        <div {...props} style={{...props.style, position: 'relative'}}>
            <LoadingMask/>
            {children}
        </div>
    );
};
