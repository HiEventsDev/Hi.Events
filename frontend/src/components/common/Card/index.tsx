import classes from './Card.module.scss';
import React, {CSSProperties} from "react";

interface CardInterface {
    children: React.ReactNode;
    className?: string;
    style?: CSSProperties | undefined;
    variant?: 'default' | 'lightGray' | 'noStyle' | 'lightGradient';
}

export const Card = ({children, className = '', style = {}, variant = 'default'}: CardInterface) => {
    return (
        <div className={`${className} ${classes.card} ${classes[variant]}`} style={style}>
            {children}
        </div>
    );
}