import {Tabs} from "@mantine/core";
import {SeatMapArea} from "./lib/types.ts";

interface AreaTabsProps {
    areas: SeatMapArea[];
    value: string;
    onChange: (areaId: string) => void;
    badge?: (areaId: string) => number;
    className?: string;
}

export const AreaTabs = ({areas, value, onChange, badge, className}: AreaTabsProps) => {
    if (areas.length <= 1) {
        return null;
    }

    return (
        <Tabs value={value} onChange={next => next && onChange(next)} variant="pills" className={className}>
            <Tabs.List>
                {areas.map(area => {
                    const count = badge?.(area.id) ?? 0;
                    return (
                        <Tabs.Tab key={area.id} value={area.id}>
                            {area.name}{count > 0 && ` · ${count}`}
                        </Tabs.Tab>
                    );
                })}
            </Tabs.List>
        </Tabs>
    );
};
