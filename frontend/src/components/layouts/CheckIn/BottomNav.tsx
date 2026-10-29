import {ReactNode} from "react";
import classes from "./BottomNav.module.scss";

export interface BottomNavTab<T extends string> {
    id: T;
    label: string;
    icon: ReactNode;
}

interface BottomNavProps<T extends string> {
    tabs: BottomNavTab<T>[];
    active: T;
    onChange: (tab: T) => void;
    ariaLabel: string;
}

export const BottomNav = <T extends string>({tabs, active, onChange, ariaLabel}: BottomNavProps<T>) => {
    return (
        <nav className={classes.nav} aria-label={ariaLabel}>
            <div className={classes.pill}>
                {tabs.map(tab => (
                    <button
                        key={tab.id}
                        type="button"
                        className={`${classes.tab} ${active === tab.id ? classes.active : ""}`}
                        onClick={() => onChange(tab.id)}
                        aria-current={active === tab.id ? "page" : undefined}
                    >
                        <span className={classes.icon}>{tab.icon}</span>
                        <span className={classes.label}>{tab.label}</span>
                    </button>
                ))}
            </div>
        </nav>
    );
};
