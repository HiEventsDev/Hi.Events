import {MultiSelect, Radio, Switch} from "@mantine/core";
import {UseFormReturnType} from "@mantine/form";
import {useDebouncedValue} from "@mantine/hooks";
import {t} from "@lingui/macro";
import {ReactNode, useEffect, useId, useRef, useState} from "react";
import {IconEye, IconLock, IconSearch, IconUser, IconUserShield, IconWorld} from "@tabler/icons-react";
import {useGetOrganizers} from "../../../queries/useGetOrganizers.ts";
import {useGetEvents} from "../../../queries/useGetEvents.ts";
import {AdjustablePermission, TeamAccessRequest, User, UserRole} from "../../../types.ts";
import classes from "./TeamAccessFields.module.scss";

type TeamAccessForm = TeamAccessRequest & { role: UserRole };

interface TeamAccessFieldsProps {
    form: UseFormReturnType<any>;
    disabled?: boolean;
}

const ADJUSTABLE: AdjustablePermission[] = ['VIEW_FINANCIALS', 'ISSUE_REFUNDS'];

const roleDefault = (role: UserRole | undefined) => role === 'ORGANIZER';

const hasPermission = (values: TeamAccessForm, permission: AdjustablePermission) =>
    values.permissions?.[permission] ?? roleDefault(values.role);

const canBeRestricted = (role: UserRole) => role === 'ORGANIZER' || role === 'VIEWER';

export const roleLabel = (role: UserRole | undefined) => {
    switch (role) {
        case 'ADMIN':
        case 'SUPERADMIN':
            return t`Admin`;
        case 'VIEWER':
            return t`Viewer`;
        default:
            return t`Organizer`;
    }
};

export const teamAccessInitialValues = (user?: Pick<User, 'role' | 'access'>): TeamAccessRequest => ({
    access_scope: user?.access?.scope ?? 'ACCOUNT',
    organizer_ids: user?.access?.organizer_ids ?? [],
    event_ids: user?.access?.event_ids ?? [],
    permissions: user?.access && user.role && canBeRestricted(user.role)
        ? Object.fromEntries(ADJUSTABLE
            .map(permission => [permission, user.access!.permissions.includes(permission)] as const)
            .filter(([, granted]) => granted !== roleDefault(user.role)))
        : {},
});

export const toTeamAccessPayload = (values: TeamAccessForm): TeamAccessRequest => {
    if (!canBeRestricted(values.role)) {
        return {access_scope: 'ACCOUNT', organizer_ids: [], event_ids: [], permissions: {}};
    }

    const isRestricted = values.access_scope === 'RESTRICTED';
    const relevant = values.role === 'ORGANIZER' ? ADJUSTABLE : ADJUSTABLE.filter(p => p !== 'ISSUE_REFUNDS');

    return {
        access_scope: values.access_scope,
        organizer_ids: isRestricted ? values.organizer_ids : [],
        event_ids: isRestricted ? values.event_ids : [],
        permissions: Object.fromEntries(relevant
            .map(permission => [permission, hasPermission(values, permission)] as const)
            .filter(([, granted]) => granted !== roleDefault(values.role))),
    };
};

export const TeamAccessFields = ({form, disabled}: TeamAccessFieldsProps) => {
    const values = form.values as TeamAccessForm;
    const isAdmin = values.role === 'ADMIN' || values.role === 'SUPERADMIN';
    const isRestricted = !isAdmin && values.access_scope === 'RESTRICTED';
    const organizersQuery = useGetOrganizers();
    const [search, setSearch] = useState('');
    const [debouncedSearch] = useDebouncedValue(search, 300);
    const eventsQuery = useGetEvents({perPage: 50, query: debouncedSearch, sortBy: 'created_at', sortDirection: 'desc'});
    const knownEvents = useRef(new Map<number, { title: string, organizerId: number }>());
    const previousRole = useRef(values.role);

    useEffect(() => {
        if (previousRole.current !== values.role) {
            previousRole.current = values.role;
            form.setFieldValue('permissions', {});
        }
    }, [values.role]);

    const roleOptions = [
        {
            icon: <IconUserShield size={18}/>,
            label: t`Admin`,
            value: 'ADMIN',
            description: t`Full access to every event, the team, payments and account settings.`,
        },
        {
            icon: <IconUser size={18}/>,
            label: t`Organizer`,
            value: 'ORGANIZER',
            description: t`Runs events: products, orders, attendees, messages and check-in.`,
        },
        {
            icon: <IconEye size={18}/>,
            label: t`Viewer`,
            value: 'VIEWER',
            description: t`Can see events, orders and attendees but can't change anything.`,
        },
    ];

    const scopeChoices = [
        {
            icon: <IconWorld size={18}/>,
            value: 'ACCOUNT',
            label: t`Whole account`,
            description: t`Every organizer and event, including new ones.`,
        },
        {
            icon: <IconLock size={18}/>,
            value: 'RESTRICTED',
            label: t`Specific organizers and events`,
            description: t`Only what you pick below.`,
        },
    ];

    const organizers = organizersQuery.data?.data ?? [];
    (eventsQuery.data?.data ?? []).forEach(event => knownEvents.current.set(Number(event.id), {
        title: String(event.title),
        organizerId: Number(event.organizer_id),
    }));
    const visibleEventIds = new Set([
        ...(eventsQuery.data?.data ?? []).map(event => Number(event.id)),
        ...values.event_ids,
    ]);
    const events = [...visibleEventIds]
        .filter(id => knownEvents.current.has(id))
        .map(id => ({id, ...knownEvents.current.get(id)!}));

    const scopeOptions = [
        {
            group: t`Organizers (includes all their events)`,
            items: organizers.map(organizer => ({value: `organizer:${organizer.id}`, label: organizer.name})),
        },
        ...organizers
            .map(organizer => ({
                group: organizer.name,
                items: events
                    .filter(event => event.organizerId === Number(organizer.id))
                    .map(event => ({value: `event:${event.id}`, label: event.title})),
            }))
            .filter(group => group.items.length > 0),
    ];

    const knownValues = new Set(scopeOptions.flatMap(group => group.items.map(item => item.value)));
    const unlistedEvents = values.event_ids
        .map(id => `event:${id}`)
        .filter(value => !knownValues.has(value))
        .map(value => ({value, label: t`Event #${value.replace('event:', '')}`}));

    const selected = [
        ...values.organizer_ids.map(id => `organizer:${id}`),
        ...values.event_ids.map(id => `event:${id}`),
    ];

    const handleScopeChange = (next: string[]) => {
        form.setFieldValue('organizer_ids', next.filter(v => v.startsWith('organizer:')).map(v => Number(v.split(':')[1])));
        form.setFieldValue('event_ids', next.filter(v => v.startsWith('event:')).map(v => Number(v.split(':')[1])));
    };

    const setPermission = (permission: AdjustablePermission, granted: boolean) => {
        form.setFieldValue('permissions', {...values.permissions, [permission]: granted});
    };

    const canSeeFinancials = hasPermission(values, 'VIEW_FINANCIALS');

    return (
        <>
            <TeamFormSection title={t`Role`}>
                <Radio.Group
                    label={t`Role`}
                    labelProps={{className: classes.hiddenLabel}}
                    value={values.role === 'SUPERADMIN' ? 'ADMIN' : values.role}
                    onChange={(value) => form.setFieldValue('role', value)}
                    error={form.errors.role}
                >
                    <div className={classes.roleGrid}>
                        {roleOptions.map(option => (
                            <Radio.Card
                                key={option.value}
                                value={option.value}
                                disabled={disabled}
                                className={classes.choice}
                                data-testid={`team-role-${option.value.toLowerCase()}`}
                            >
                                <div className={classes.choiceHead}>
                                    <span className={classes.choiceIcon}>{option.icon}</span>
                                    <Radio.Indicator size="xs"/>
                                </div>
                                <span className={classes.choiceLabel}>{option.label}</span>
                                <span className={classes.choiceDescription}>{option.description}</span>
                            </Radio.Card>
                        ))}
                    </div>
                </Radio.Group>
            </TeamFormSection>

            {!isAdmin && (
                <TeamFormSection title={t`Access`}>
                    <Radio.Group
                        label={t`Access`}
                        labelProps={{className: classes.hiddenLabel}}
                        value={values.access_scope}
                        onChange={(value) => form.setFieldValue('access_scope', value)}
                    >
                        <div className={classes.scopeGrid}>
                            {scopeChoices.map(option => (
                                <Radio.Card
                                    key={option.value}
                                    value={option.value}
                                    disabled={disabled}
                                    className={`${classes.choice} ${classes.scopeChoice}`}
                                >
                                    <span className={classes.choiceIcon}>{option.icon}</span>
                                    <span className={classes.scopeText}>
                                        <span className={classes.choiceLabel}>{option.label}</span>
                                        <span className={classes.choiceDescription}>{option.description}</span>
                                    </span>
                                    <Radio.Indicator size="xs"/>
                                </Radio.Card>
                            ))}
                        </div>
                    </Radio.Group>

                    {isRestricted && (
                        <MultiSelect
                            className={classes.picker}
                            aria-label={t`Organizers and events`}
                            description={t`An organizer gives access to all of its events, including new ones.`}
                            placeholder={t`Search organizers and events`}
                            leftSection={<IconSearch size={16}/>}
                            searchable
                            searchValue={search}
                            onSearchChange={setSearch}
                            disabled={disabled}
                            data={[...scopeOptions, ...(unlistedEvents.length ? [{group: t`Other events`, items: unlistedEvents}] : [])]}
                            value={selected}
                            onChange={handleScopeChange}
                            error={form.errors.access_scope}
                        />
                    )}
                </TeamFormSection>
            )}

            {!isAdmin && (
                <TeamFormSection title={t`Permissions`}>
                    <div className={classes.permissions}>
                        <PermissionRow
                            label={t`Can see financials`}
                            description={t`Revenue, order totals, reports, payouts and payment settings.`}
                            checked={canSeeFinancials}
                            disabled={disabled}
                            onChange={(checked) => setPermission('VIEW_FINANCIALS', checked)}
                        />
                        {values.role === 'ORGANIZER' && (
                            <PermissionRow
                                label={t`Can issue refunds`}
                                description={t`Refund, cancel with a refund, and mark orders as paid.`}
                                hint={canSeeFinancials ? undefined : t`Turn on Can see financials to allow refunds.`}
                                checked={canSeeFinancials && hasPermission(values, 'ISSUE_REFUNDS')}
                                disabled={disabled || !canSeeFinancials}
                                onChange={(checked) => setPermission('ISSUE_REFUNDS', checked)}
                            />
                        )}
                    </div>
                </TeamFormSection>
            )}
        </>
    );
};

interface PermissionRowProps {
    label: string;
    description: string;
    hint?: string;
    checked: boolean;
    disabled?: boolean;
    onChange: (checked: boolean) => void;
}

const PermissionRow = ({label, description, hint, checked, disabled, onChange}: PermissionRowProps) => {
    const descriptionId = useId();

    return (
        <label className={`${classes.permission} ${disabled ? classes.permissionDisabled : ''}`}>
            <span className={classes.permissionText}>
                <span className={classes.choiceLabel}>{label}</span>
                <span className={classes.choiceDescription} id={descriptionId}>
                    {description}
                    {hint && <span className={classes.permissionHint}>{hint}</span>}
                </span>
            </span>
            <Switch
                aria-label={label}
                aria-describedby={descriptionId}
                checked={checked}
                disabled={disabled}
                onChange={(event) => onChange(event.currentTarget.checked)}
            />
        </label>
    );
};

export const TeamFormSection = ({title, children}: { title: string, children: ReactNode }) => (
    <section className={classes.section}>
        <h3 className={classes.sectionTitle}>{title}</h3>
        {children}
    </section>
);
