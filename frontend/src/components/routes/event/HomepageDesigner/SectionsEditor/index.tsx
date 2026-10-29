import {useEffect, useRef, useState} from "react";
import {t} from "@lingui/macro";
import {ActionIcon, Button, Menu, TextInput, Tooltip} from "@mantine/core";
import {
    IconAlignLeft,
    IconBuildingStore,
    IconCalendarEvent,
    IconCalendarPlus,
    IconChevronDown,
    IconCircleDashed,
    IconEye,
    IconEyeOff,
    IconGripVertical,
    IconHistory,
    IconHelpCircle,
    IconInfoCircle,
    IconMail,
    IconMapPin,
    IconMicrophone2,
    IconPhoto,
    IconPlayerPlay,
    IconPlus,
    IconTicket,
    IconTimeline,
    IconTrash,
    IconUsersGroup,
    TablerIcon,
} from "@tabler/icons-react";
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    KeyboardSensor,
    PointerSensor,
    TouchSensor,
    useSensor,
    useSensors,
} from "@dnd-kit/core";
import {arrayMove, SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy} from "@dnd-kit/sortable";
import {CSS} from "@dnd-kit/utilities";
import {HomepageSection, HomepageSectionType, IdParam, Image} from "../../../../../types.ts";
import {
    CUSTOM_SECTION_TYPES,
    newSectionId,
    sectionTypeDescription,
    sectionTypeLabel,
} from "../../../../../utilites/homepageSections.ts";
import {SectionChanges, SectionContentEditor, sectionExceedsLimits, SectionsEditorVariant} from "./SectionContentEditor.tsx";
import {SectionEmptyState} from "./sectionEmptyState.ts";
import {useSortableAccessibility} from "../../../../../hooks/useSortableAccessibility.ts";
import {useAccess} from "../../../../../hooks/useAccess.ts";
import {useHomepageSaveGuard} from "../../../../common/HomepageDesigner";
import {NavLink} from "react-router";
import classes from "./SectionsEditor.module.scss";

const ICONS: Record<HomepageSectionType, TablerIcon> = {
    TICKETS: IconTicket,
    EVENTS: IconCalendarEvent,
    CONTACT: IconMail,
    PAST_EVENTS: IconHistory,
    MORE_EVENTS: IconCalendarPlus,
    ABOUT: IconInfoCircle,
    LINEUP: IconMicrophone2,
    SCHEDULE: IconTimeline,
    LOCATION: IconMapPin,
    ORGANIZER: IconBuildingStore,
    TEXT: IconAlignLeft,
    GALLERY: IconPhoto,
    FAQ: IconHelpCircle,
    VIDEO: IconPlayerPlay,
    SPONSORS: IconUsersGroup,
};

interface SectionsEditorProps {
    variant: SectionsEditorVariant;
    entityId: IdParam;
    sections: HomepageSection[];
    images: Image[];
    emptyStates?: Map<string, SectionEmptyState>;
    ticketsBesideContent?: boolean;
    disabled?: boolean;
    onChange: (update: (sections: HomepageSection[]) => HomepageSection[]) => void;
    onImagesChanged: () => void;
}

const NO_EMPTY_STATES = new Map<string, SectionEmptyState>();

const MAX_SECTIONS = 41;

const revealInvalidField = (row: HTMLElement) => {
    const field = row.querySelector<HTMLElement>('[aria-invalid="true"]');
    if (!field) {
        row.querySelector<HTMLElement>('[contenteditable="true"]')?.focus({preventScroll: true});
        row.scrollIntoView({block: 'end'});
        return;
    }
    const [messageId] = field.getAttribute('aria-describedby')?.split(' ') ?? [];
    const message = messageId ? document.getElementById(messageId) : null;
    (message ?? field).scrollIntoView({block: 'center', behavior: 'smooth'});
    field.focus({preventScroll: true});
};

const sectionLabel = (section: HomepageSection): string =>
    (section.type === 'TICKETS' ? null : section.heading) || sectionTypeLabel(section.type);

export const SectionsEditor = ({variant, entityId, sections, images, emptyStates = NO_EMPTY_STATES, ticketsBesideContent = false, disabled, onChange, onImagesChanged}: SectionsEditorProps) => {
    const alwaysShownType: HomepageSectionType = variant === 'event' ? 'TICKETS' : 'EVENTS';

    const [expandedId, setExpandedId] = useState<string | null>(null);
    const editorRef = useRef<HTMLDivElement>(null);
    const [invalidFieldRequest, setInvalidFieldRequest] = useState(0);
    const {id: dndId, accessibility} = useSortableAccessibility(
        sections.map(section => section.id),
        id => {
            const section = sections.find(candidate => candidate.id === id);
            return section ? sectionLabel(section) : '';
        },
    );
    const sensors = useSensors(
        useSensor(PointerSensor, {activationConstraint: {distance: 4}}),
        useSensor(TouchSensor, {activationConstraint: {delay: 150, tolerance: 6}}),
        useSensor(KeyboardSensor, {coordinateGetter: sortableKeyboardCoordinates}),
    );

    useHomepageSaveGuard(() => {
        const invalidSection = sections.find(sectionExceedsLimits);
        if (!invalidSection) {
            return false;
        }
        setExpandedId(invalidSection.id);
        setInvalidFieldRequest(request => request + 1);
        return true;
    });

    useEffect(() => {
        if (!invalidFieldRequest) {
            return;
        }
        const frame = requestAnimationFrame(() => {
            const row = editorRef.current?.querySelector<HTMLElement>('[data-expanded]');
            if (row) {
                revealInvalidField(row);
            }
        });
        return () => cancelAnimationFrame(frame);
    }, [invalidFieldRequest]);

    const update = (id: string, changes: SectionChanges) => onChange(current => current.map(section => section.id === id
        ? {...section, ...(typeof changes === 'function' ? changes(section) : changes)}
        : section));

    const handleDragEnd = ({active, over}: DragEndEvent) => {
        if (!over || active.id === over.id) {
            return;
        }
        onChange(current => {
            const ids = current.map(section => section.id);
            return arrayMove(current, ids.indexOf(String(active.id)), ids.indexOf(String(over.id)));
        });
    };

    const addButtonProps = {
        variant: 'light',
        size: 'xs',
        leftSection: <IconPlus size={14}/>,
        className: classes.addButton,
        'data-testid': 'homepage-add-section-button',
    } as const;

    const addSection = (type: HomepageSectionType) => {
        const section: HomepageSection = {id: newSectionId(), type, enabled: true};
        onChange(current => [...current, section]);
        setExpandedId(section.id);
    };

    return (
        <div className={classes.editor} ref={editorRef}>
            <p className={classes.help}>
                {variant === 'event'
                    ? t`Drag to reorder. Sections marked Empty appear on your event page once they have content.`
                    : t`Drag to reorder. Hidden sections stay saved but don't show on your organizer page.`}
            </p>

            <DndContext id={dndId} accessibility={accessibility} sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
                <SortableContext items={sections.map(section => section.id)} strategy={verticalListSortingStrategy}>
                    <ul className={classes.list} data-testid="homepage-sections-list">
                        {sections.map(section => (
                            <SectionRow
                                key={section.id}
                                entityId={entityId}
                                section={section}
                                emptyState={emptyStates.get(section.id)}
                                alwaysShown={section.type === alwaysShownType}
                                expanded={expandedId === section.id}
                                disabled={disabled}
                                onToggleExpanded={() => setExpandedId(expandedId === section.id ? null : section.id)}
                                onChange={changes => update(section.id, changes)}
                                onRemove={() => onChange(current => current.filter(candidate => candidate.id !== section.id))}
                            >
                                <SectionContentEditor
                                    variant={variant}
                                    entityId={entityId}
                                    section={section}
                                    images={images}
                                    ticketsBesideContent={ticketsBesideContent}
                                    disabled={disabled}
                                    isEmpty={emptyStates.has(section.id)}
                                    onChange={changes => update(section.id, changes)}
                                    onImagesChanged={onImagesChanged}
                                />
                            </SectionRow>
                        ))}
                    </ul>
                </SortableContext>
            </DndContext>

            {sections.length >= MAX_SECTIONS ? (
                <Tooltip label={t`You can add up to ${MAX_SECTIONS} sections.`} position="top-start" events={{hover: true, focus: true, touch: true}}>
                    <Button {...addButtonProps} data-disabled aria-disabled>
                        {t`Add section`}
                    </Button>
                </Tooltip>
            ) : (
                <Menu position="bottom-start" width={300} disabled={disabled}>
                    <Menu.Target>
                        <Button {...addButtonProps}>
                            {t`Add section`}
                        </Button>
                    </Menu.Target>
                    <Menu.Dropdown>
                        {CUSTOM_SECTION_TYPES.map(type => {
                            const Icon = ICONS[type];
                            return (
                                <Menu.Item
                                    key={type}
                                    leftSection={<Icon size={16}/>}
                                    onClick={() => addSection(type)}
                                    data-testid={`homepage-add-section-${type.toLowerCase()}`}
                                >
                                    <div className={classes.menuLabel}>{sectionTypeLabel(type)}</div>
                                    <div className={classes.menuDescription}>{sectionTypeDescription(type)}</div>
                                </Menu.Item>
                            );
                        })}
                    </Menu.Dropdown>
                </Menu>
            )}
        </div>
    );
};

interface SectionRowProps {
    entityId: IdParam;
    section: HomepageSection;
    alwaysShown: boolean;
    emptyState?: SectionEmptyState;
    expanded: boolean;
    disabled?: boolean;
    onToggleExpanded: () => void;
    onChange: (changes: Partial<HomepageSection>) => void;
    onRemove: () => void;
    children: React.ReactNode;
}

const SectionRow = ({entityId, section, alwaysShown, emptyState, expanded, disabled, onToggleExpanded, onChange, onRemove, children}: SectionRowProps) => {
    const {attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging} = useSortable({
        id: section.id,
        disabled,
    });
    const Icon = ICONS[section.type];
    const isCustom = CUSTOM_SECTION_TYPES.includes(section.type);
    const titledByCategories = section.type === 'TICKETS';
    const heading = titledByCategories ? null : section.heading;
    const label = sectionLabel(section);
    const {canManageOrganizer} = useAccess();
    const emptyAction = emptyState?.action
        && (emptyState.action.organizerId === undefined || canManageOrganizer(emptyState.action.organizerId))
        ? emptyState.action
        : undefined;

    return (
        <li
            ref={setNodeRef}
            className={classes.row}
            data-expanded={expanded || undefined}
            data-hidden={!section.enabled || undefined}
            data-empty={(section.enabled && !!emptyState) || undefined}
            data-dragging={isDragging || undefined}
            data-testid={`homepage-section-row-${section.type.toLowerCase()}`}
            style={{transform: CSS.Transform.toString(transform), transition}}
        >
            <div className={classes.rowHeader}>
                <button type="button" ref={setActivatorNodeRef} className={classes.grip} aria-label={t`Drag to reorder ${label}`} {...attributes} {...listeners}>
                    <IconGripVertical size={14}/>
                </button>
                <button type="button" className={classes.rowTitle} onClick={onToggleExpanded} aria-expanded={expanded}>
                    <span className={classes.rowIcon}><Icon size={15}/></span>
                    <span className={classes.rowLabel}>{label}</span>
                    {heading && <span className={classes.rowType}>{sectionTypeLabel(section.type)}</span>}
                </button>
                {section.enabled && emptyState && (
                    <Tooltip label={emptyState.reason} multiline w={220}>
                        <span className={classes.emptyBadge} data-testid={`homepage-section-empty-${section.type.toLowerCase()}`}>{t`Empty`}</span>
                    </Tooltip>
                )}
                <Tooltip label={alwaysShown ? (section.type === 'TICKETS' ? t`Tickets are always shown` : t`Events are always shown`) : section.enabled ? t`Hide section` : t`Show section`}>
                    <ActionIcon
                        variant="subtle"
                        color={section.enabled ? 'gray' : 'orange'}
                        disabled={disabled || alwaysShown}
                        onClick={() => onChange({enabled: !section.enabled})}
                        aria-label={section.enabled ? t`Hide ${label}` : t`Show ${label}`}
                        data-testid={`homepage-section-toggle-${section.type.toLowerCase()}`}
                    >
                        {section.enabled ? <IconEye size={16}/> : <IconEyeOff size={16}/>}
                    </ActionIcon>
                </Tooltip>
                <ActionIcon variant="subtle" color="gray" onClick={onToggleExpanded} aria-label={t`Section settings for ${label}`}>
                    <IconChevronDown size={16} className={classes.chevron}/>
                </ActionIcon>
            </div>

            {expanded && (
                <div className={classes.rowBody}>
                    {emptyState && (
                        <div className={classes.emptyNotice}>
                            <IconCircleDashed size={16}/>
                            <span>
                                {t`Not shown yet.`} {emptyState.reason}
                                {emptyAction && (
                                    <>
                                        {' '}<NavLink to={emptyAction.to}>{emptyAction.label}</NavLink>
                                    </>
                                )}
                            </span>
                        </div>
                    )}
                    {titledByCategories
                        ? (
                            <div className={classes.emptyNotice} data-testid="homepage-section-tickets-note">
                                <IconTicket size={16}/>
                                <span>
                                    {t`Ticket titles come from your product categories.`}
                                    {' '}<NavLink to={`/manage/event/${entityId}/products`}>{t`Edit in Tickets & Products`}</NavLink>
                                </span>
                            </div>
                        )
                        : (
                            <TextInput
                                size="xs"
                                label={t`Heading`}
                                placeholder={sectionTypeLabel(section.type)}
                                value={section.heading ?? ''}
                                maxLength={100}
                                disabled={disabled}
                                onChange={event => onChange({heading: event.currentTarget.value || null})}
                            />
                        )}
                    {children}
                    {isCustom && (
                        <Button
                            variant="subtle"
                            color="red"
                            size="xs"
                            leftSection={<IconTrash size={14}/>}
                            className={classes.removeButton}
                            disabled={disabled}
                            onClick={onRemove}
                        >
                            {t`Remove section`}
                        </Button>
                    )}
                </div>
            )}
        </li>
    );
};
