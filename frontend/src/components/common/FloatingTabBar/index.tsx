import {ReactNode} from "react";
import classes from "./FloatingTabBar.module.scss";

export interface FloatingTabBarItem<T extends string> {
    id: T;
    label: string;
    icon: ReactNode;
}

interface FloatingTabBarProps<T extends string> {
    ariaLabel: string;
    active: T;
    items: FloatingTabBarItem<T>[];
    onChange: (id: T) => void;
}

export const FloatingTabBar = <T extends string>({ariaLabel, active, items, onChange}: FloatingTabBarProps<T>) => {
    return (
        <nav className={classes.nav} aria-label={ariaLabel}>
            <div className={classes.pill}>
                {items.map((item) => (
                    <button
                        key={item.id}
                        type="button"
                        className={`${classes.tab} ${active === item.id ? classes.active : ""}`}
                        onClick={() => onChange(item.id)}
                        aria-current={active === item.id ? "page" : undefined}
                    >
                        <span className={classes.icon}>{item.icon}</span>
                        <span className={classes.label}>{item.label}</span>
                    </button>
                ))}
            </div>
        </nav>
    );
};
