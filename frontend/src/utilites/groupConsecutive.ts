export interface ConsecutiveGroup<T> {
    key: string;
    items: T[];
}

export const groupConsecutive = <T>(items: T[], keyOf: (item: T) => string): ConsecutiveGroup<T>[] =>
    items.reduce<ConsecutiveGroup<T>[]>((groups, item) => {
        const key = keyOf(item);
        const last = groups[groups.length - 1];
        if (last?.key === key) {
            last.items.push(item);
        } else {
            groups.push({key, items: [item]});
        }
        return groups;
    }, []);
