import {Combobox, Input, InputBase, useCombobox} from "@mantine/core";
import {t} from "@lingui/macro";
import {useLingui} from "@lingui/react";
import {ReactNode, useEffect, useMemo, useRef, useState} from "react";
import {CountryCode, getCountries, getCountryCallingCode, parsePhoneNumberFromString} from "libphonenumber-js";
import countries from "../../../../data/countries.json";
import {toIntlLocale} from "../../../locales.ts";
import classes from "./PhoneNumberInput.module.scss";

interface PhoneNumberInputProps {
    value?: string | null;
    onChange?: (value: string) => void;
    onBlur?: () => void;
    onFocus?: () => void;
    label?: ReactNode;
    description?: ReactNode;
    error?: ReactNode;
    withAsterisk?: boolean;
    disabled?: boolean;
    defaultCountry?: string | null;
}

interface CountryOption {
    code: CountryCode;
    name: string;
    callingCode: string;
    searchTerms: string[];
}

const SUPPORTED_COUNTRIES = new Set<string>(getCountries());

const FALLBACK_COUNTRY: CountryCode = 'US';

const toCountryCode = (value?: string | null): CountryCode | null => {
    const upper = value?.toUpperCase();
    return upper && SUPPORTED_COUNTRIES.has(upper) ? upper as CountryCode : null;
};

const localeCountry = (): CountryCode | null => {
    if (typeof navigator === 'undefined') {
        return null;
    }
    const languages = (navigator.languages?.length ? navigator.languages : [navigator.language]).filter(Boolean);
    try {
        const locales = languages.map(language => new Intl.Locale(language));
        const region = locales.find(locale => locale.region)?.region ?? locales[0]?.maximize().region;
        return toCountryCode(region);
    } catch {
        return null;
    }
};

const foldForSearch = (value: string): string => value.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

const countryOptions = (locale: string): CountryOption[] => {
    const intlLocale = toIntlLocale(locale) ?? 'en';
    const localizedNames = new Intl.DisplayNames([intlLocale, 'en'], {type: 'region'});

    return (countries as { value: string, label: string }[])
        .filter(item => SUPPORTED_COUNTRIES.has(item.value))
        .map(item => {
            const name = localizedNames.of(item.value) ?? item.label;
            return {
                code: item.value as CountryCode,
                name,
                callingCode: getCountryCallingCode(item.value as CountryCode),
                searchTerms: [foldForSearch(name), foldForSearch(item.label)],
            };
        })
        .sort((a, b) => a.name.localeCompare(b.name, intlLocale));
};

const splitNumber = (value: string | null | undefined, fallback: CountryCode): { country: CountryCode, national: string } => {
    const parsed = value ? parsePhoneNumberFromString(value) : undefined;
    if (parsed?.country) {
        return {country: parsed.country, national: parsed.formatNational()};
    }
    return {country: fallback, national: ''};
};

const toE164 = (national: string, country: CountryCode): string => {
    const trimmed = national.trim();
    if (trimmed === '') {
        return '';
    }
    const parsed = trimmed.startsWith('+')
        ? parsePhoneNumberFromString(trimmed)
        : parsePhoneNumberFromString(trimmed, country);
    if (parsed) {
        return parsed.number;
    }
    const digits = trimmed.replace(/\D/g, '').replace(/^0+/, '');
    return trimmed.startsWith('+') ? `+${digits}` : `+${getCountryCallingCode(country)}${digits}`;
};

export const PhoneNumberInput = ({
    value,
    onChange,
    onBlur,
    onFocus,
    label,
    description,
    error,
    withAsterisk,
    disabled,
    defaultCountry,
}: PhoneNumberInputProps) => {
    const {i18n} = useLingui();
    const initialCountry = toCountryCode(defaultCountry) ?? FALLBACK_COUNTRY;
    const [{country, national}, setParts] = useState(() => splitNumber(value, initialCountry));
    const [search, setSearch] = useState('');
    const lastEmitted = useRef<string>(value ?? '');
    const combobox = useCombobox({
        onDropdownClose: () => {
            combobox.resetSelectedOption();
            setSearch('');
        },
        onDropdownOpen: () => {
            combobox.focusSearchInput();
            combobox.updateSelectedOptionIndex('active', {scrollIntoView: true});
        },
    });

    const options = useMemo(() => countryOptions(i18n.locale), [i18n.locale]);

    useEffect(() => {
        if ((value ?? '') !== lastEmitted.current) {
            lastEmitted.current = value ?? '';
            setParts(splitNumber(value, country));
        }
    }, [value]);

    useEffect(() => {
        if (!value && !toCountryCode(defaultCountry)) {
            const fromLocale = localeCountry();
            if (fromLocale) {
                setParts(parts => parts.national === '' ? {...parts, country: fromLocale} : parts);
            }
        }
    }, []);

    const emit = (nextNational: string, nextCountry: CountryCode) => {
        let resolvedCountry = nextCountry;
        let resolvedNational = nextNational;
        if (nextNational.trim().startsWith('+')) {
            const parsed = parsePhoneNumberFromString(nextNational.trim());
            if (parsed?.country) {
                resolvedCountry = parsed.country;
                resolvedNational = parsed.formatNational();
            }
        }
        setParts({country: resolvedCountry, national: resolvedNational});
        const next = toE164(resolvedNational, resolvedCountry);
        lastEmitted.current = next;
        onChange?.(next);
    };

    const query = foldForSearch(search.trim()).replace(/^\+/, '');
    const matchesCode = (option: CountryOption) => option.code.toLowerCase() === query;
    const filtered = query === ''
        ? options
        : [
            ...options.filter(matchesCode),
            ...options.filter(option => !matchesCode(option) && (
                option.searchTerms.some(term => term.includes(query)) || option.callingCode.startsWith(query)
            )),
        ];

    return (
        <Input.Wrapper
            label={label}
            description={description}
            error={error}
            withAsterisk={withAsterisk}
        >
            <div className={classes.row}>
                <Combobox
                    store={combobox}
                    withinPortal
                    position="bottom-start"
                    disabled={disabled}
                    onOptionSubmit={(code) => {
                        emit(national, code as CountryCode);
                        combobox.closeDropdown();
                    }}
                >
                    <Combobox.Target>
                        <InputBase
                            component="button"
                            type="button"
                            pointer
                            disabled={disabled}
                            className={classes.country}
                            rightSection={<Combobox.Chevron/>}
                            rightSectionPointerEvents="none"
                            onClick={() => combobox.toggleDropdown()}
                            aria-label={t`Country code`}
                            error={!!error}
                        >
                            {country} +{getCountryCallingCode(country)}
                        </InputBase>
                    </Combobox.Target>
                    <Combobox.Dropdown miw={260}>
                        <Combobox.Search
                            value={search}
                            onChange={(event) => setSearch(event.currentTarget.value)}
                            placeholder={t`Search countries`}
                        />
                        <Combobox.Options mah={260} style={{overflowY: 'auto'}}>
                            {filtered.length === 0
                                ? <Combobox.Empty>{t`No countries found`}</Combobox.Empty>
                                : filtered.map(option => (
                                    <Combobox.Option
                                        value={option.code}
                                        key={option.code}
                                        active={option.code === country}
                                        className={classes.option}
                                    >
                                        {option.name} <span className={classes.callingCode}>+{option.callingCode}</span>
                                    </Combobox.Option>
                                ))}
                        </Combobox.Options>
                    </Combobox.Dropdown>
                </Combobox>
                <Input
                    className={classes.number}
                    type="tel"
                    inputMode="tel"
                    autoComplete="tel"
                    disabled={disabled}
                    error={!!error}
                    value={national}
                    onChange={(event) => emit(event.currentTarget.value, country)}
                    onBlur={onBlur}
                    onFocus={onFocus}
                    aria-label={label ? undefined : t`Mobile number`}
                />
            </div>
        </Input.Wrapper>
    );
};
