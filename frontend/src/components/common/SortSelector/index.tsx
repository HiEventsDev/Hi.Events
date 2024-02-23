import {Select} from "@mantine/core";
import classes from "./SortSelector.module.scss";
import {SortDirectionLabel} from "../../../types.ts";

export interface SortSelectorProps {
    options: Record<string, SortDirectionLabel>,
    onSortSelect: (key: string, sortDirection: string) => void,
    selected: string,
}

export const SortSelector = ({options, onSortSelect, selected}: SortSelectorProps) => {
    const sortOptions = Object.entries(options).flatMap(([key, {asc, desc}]) => {
        const optionsForThisKey = [];
        if (asc) {
            optionsForThisKey.push({value: `${key}:asc`, label: asc});
        }
        if (desc) {
            optionsForThisKey.push({value: `${key}:desc`, label: desc});
        }
        return optionsForThisKey;
    });

    return (
        <div className={classes.selectWrapper}>
            <Select
                size={'md'}
                data={sortOptions}
                className={classes.select}
                value={selected}
                onChange={(value) => {
                    if (value) {
                        const [key, sortDirection] = value.split(':');
                        onSortSelect(key, sortDirection);
                    }
                }}
            />
        </div>
    );
}
