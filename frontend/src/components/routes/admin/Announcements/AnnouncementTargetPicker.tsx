import {MultiSelect} from "@mantine/core";
import {t} from "@lingui/macro";
import {useEffect, useMemo, useState} from "react";
import {useGetAllAccounts} from "../../../../queries/useGetAllAccounts";
import {useGetAllUsers} from "../../../../queries/useGetAllUsers";
import {AnnouncementTargetType} from "../../../../api/announcement.client";

interface AnnouncementTargetPickerProps {
    targetType: Exclude<AnnouncementTargetType, 'ALL'>;
    value: number[];
    onChange: (ids: number[]) => void;
    initialLabels: Record<number, string>;
    error?: string;
}

interface TargetOption {
    id: number;
    label: string;
}

interface PickerBaseProps extends Omit<AnnouncementTargetPickerProps, 'targetType'> {
    label: string;
    searchResults: TargetOption[];
    isFetching: boolean;
    search: string;
    onSearchChange: (search: string) => void;
}

const PickerBase = ({label, searchResults, isFetching, search, onSearchChange, value, onChange, initialLabels, error}: PickerBaseProps) => {
    const [knownLabels, setKnownLabels] = useState<Record<number, string>>(initialLabels);

    useEffect(() => {
        if (!searchResults.length) {
            return;
        }
        setKnownLabels((labels) => ({
            ...labels,
            ...Object.fromEntries(searchResults.map((result) => [result.id, result.label])),
        }));
    }, [searchResults]);

    const options = useMemo(() => {
        const ids = new Set([...searchResults.map((result) => result.id), ...value]);
        return [...ids].map((id) => ({
            value: id.toString(),
            label: knownLabels[id] || `#${id}`,
        }));
    }, [searchResults, value, knownLabels]);

    return (
        <MultiSelect
            label={label}
            description={t`Search by name or email and select one or more`}
            placeholder={t`Type to search...`}
            data={options}
            value={value.map((id) => id.toString())}
            onChange={(ids) => onChange(ids.map(Number))}
            searchable
            searchValue={search}
            onSearchChange={onSearchChange}
            filter={({options}) => options}
            nothingFoundMessage={isFetching ? t`Searching...` : t`No results found`}
            error={error}
            data-testid="announcement-target-picker"
        />
    );
};

const useDebouncedSearch = () => {
    const [search, setSearch] = useState("");
    const [debouncedSearch, setDebouncedSearch] = useState("");

    useEffect(() => {
        const timer = setTimeout(() => setDebouncedSearch(search), 300);
        return () => clearTimeout(timer);
    }, [search]);

    return {search, setSearch, debouncedSearch};
};

const AccountPicker = (props: Omit<AnnouncementTargetPickerProps, 'targetType'>) => {
    const {search, setSearch, debouncedSearch} = useDebouncedSearch();
    const {data, isFetching} = useGetAllAccounts({search: debouncedSearch, per_page: 20});

    const searchResults = useMemo(() => (data?.data || []).map((account) => ({
        id: Number(account.id),
        label: `${account.name} (${account.email})`,
    })), [data]);

    return (
        <PickerBase
            {...props}
            label={t`Target accounts`}
            searchResults={searchResults}
            isFetching={isFetching}
            search={search}
            onSearchChange={setSearch}
        />
    );
};

const UserPicker = (props: Omit<AnnouncementTargetPickerProps, 'targetType'>) => {
    const {search, setSearch, debouncedSearch} = useDebouncedSearch();
    const {data, isFetching} = useGetAllUsers({search: debouncedSearch, per_page: 20});

    const searchResults = useMemo(() => (data?.data || []).map((user) => ({
        id: Number(user.id),
        label: `${user.full_name || user.email} (${user.email})`,
    })), [data]);

    return (
        <PickerBase
            {...props}
            label={t`Target users`}
            searchResults={searchResults}
            isFetching={isFetching}
            search={search}
            onSearchChange={setSearch}
        />
    );
};

export const AnnouncementTargetPicker = ({targetType, ...props}: AnnouncementTargetPickerProps) => {
    return targetType === 'ACCOUNTS'
        ? <AccountPicker key="accounts" {...props}/>
        : <UserPicker key="users" {...props}/>;
};
