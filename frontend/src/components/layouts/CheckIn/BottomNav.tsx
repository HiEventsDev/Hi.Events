import {t} from "@lingui/macro";
import {IconChartBar, IconQrcode, IconSearch} from "@tabler/icons-react";
import {FloatingTabBar} from "../../common/FloatingTabBar";

export type CheckInTab = "scan" | "search" | "stats";

interface BottomNavProps {
    active: CheckInTab;
    onChange: (tab: CheckInTab) => void;
}

export const BottomNav = ({active, onChange}: BottomNavProps) => (
    <FloatingTabBar
        ariaLabel={t`Check-in navigation`}
        active={active}
        onChange={onChange}
        items={[
            {id: "scan", label: t`Scan`, icon: <IconQrcode size={22} stroke={1.7}/>},
            {id: "search", label: t`Search`, icon: <IconSearch size={20} stroke={1.8}/>},
            {id: "stats", label: t`Stats`, icon: <IconChartBar size={20} stroke={1.8}/>},
        ]}
    />
);
