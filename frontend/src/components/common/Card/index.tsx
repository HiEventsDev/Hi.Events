import classes from './Card.module.scss';
import React, {CSSProperties, LegacyRef} from "react";

export type CardVariant = 'default' | 'lightGray' | 'noStyle' | 'lightGradient';

interface CardInterface {
    children: React.ReactNode;
    className?: string;
    style?: CSSProperties | undefined;
    variant?: CardVariant;
    ref?: LegacyRef<HTMLDivElement> | undefined;
}

export const Card = ({children, className = '', style = {}, variant = 'default', ref = null}: CardInterface) => {
    return (
        <div className={`${className} ${classes.card} ${classes[variant]}`}
             style={style}
             ref={ref}
        >
            {children}
        </div>
    );
}
