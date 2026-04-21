import {t} from "@lingui/macro";
import {IconChartBar, IconQrcode, IconSearch} from "@tabler/icons-react";
import classes from "./BottomNav.module.scss";

export type CheckInTab = "scan" | "search" | "stats";

interface BottomNavProps {
    active: CheckInTab;
    onChange: (tab: CheckInTab) => void;
}

export const BottomNav = ({active, onChange}: BottomNavProps) => {
    const tabs: { id: CheckInTab; label: string; icon: JSX.Element }[] = [
        {id: "scan", label: t`Scan`, icon: <IconQrcode size={22} stroke={1.7}/>},
        {id: "search", label: t`Search`, icon: <IconSearch size={20} stroke={1.8}/>},
        {id: "stats", label: t`Stats`, icon: <IconChartBar size={20} stroke={1.8}/>},
    ];

    return (
        <nav className={classes.nav} aria-label={t`Check-in navigation`}>
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
