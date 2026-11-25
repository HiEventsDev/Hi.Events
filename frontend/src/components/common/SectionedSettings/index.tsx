import {ComponentType, createContext, MouseEvent, useCallback, useContext, useEffect, useId, useRef, useState} from "react";
import {Navigate, NavLink, useLocation, useNavigate, useParams} from "react-router";
import {Select} from "@mantine/core";
import {t} from "@lingui/macro";
import classNames from "classnames";
import {PageBody} from "../PageBody";
import classes from "./SectionedSettings.module.scss";
import {useUnsavedChangesGuard} from "../../../hooks/useUnsavedChangesGuard.ts";

export interface SettingsSection {
    slug: string;
    anchorId: string;
    label: string;
    icon: ComponentType<{ size?: number; stroke?: number }>;
    component: ComponentType;
    tone?: 'danger';
}

export interface SettingsSectionGroup {
    label: string;
    sections: SettingsSection[];
}

interface SectionedSettingsProps {
    navLabel: string;
    basePath: string;
    groups: SettingsSectionGroup[];
    isReady: boolean;
}

interface SettingsSubsection {
    id: string;
    label: string;
}

const UnsavedSettingsContext = createContext<((id: string, isDirty: boolean) => void) | null>(null);
const SettingsSubsectionContext = createContext<((subsection: SettingsSubsection) => () => void) | null>(null);

export const useRegisterSettingsSubsection = (id?: string, label?: string) => {
    const register = useContext(SettingsSubsectionContext);

    useEffect(() => {
        if (!register || !id || !label) {
            return;
        }
        return register({id, label});
    }, [register, id, label]);
};

const bySubsectionDocumentOrder = (a: SettingsSubsection, b: SettingsSubsection) => {
    const first = document.getElementById(a.id);
    const second = document.getElementById(b.id);
    if (!first || !second) {
        return 0;
    }
    return first.compareDocumentPosition(second) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1;
};

const SUBSECTION_ACTIVATION_OFFSET = 96;
const NO_SUBSECTIONS: SettingsSubsection[] = [];

const useActiveSubsection = (subsections: SettingsSubsection[]) => {
    const [activeId, setActiveId] = useState<string | null>(null);

    useEffect(() => {
        const main = document.getElementById('app-manage-main');
        if (!main || subsections.length === 0) {
            setActiveId(null);
            return;
        }

        let frame = 0;
        const update = () => {
            frame = 0;
            const threshold = main.getBoundingClientRect().top + SUBSECTION_ACTIVATION_OFFSET;
            const isAtBottom = main.scrollTop + main.clientHeight >= main.scrollHeight - 2;
            let current: string | null = null;
            for (const subsection of subsections) {
                const element = document.getElementById(subsection.id);
                if (element && (isAtBottom || element.getBoundingClientRect().top <= threshold)) {
                    current = subsection.id;
                }
            }
            setActiveId(current);
        };
        const schedule = () => {
            if (!frame) {
                frame = requestAnimationFrame(update);
            }
        };

        update();
        main.addEventListener('scroll', schedule, {passive: true});
        window.addEventListener('resize', schedule);
        return () => {
            main.removeEventListener('scroll', schedule);
            window.removeEventListener('resize', schedule);
            cancelAnimationFrame(frame);
        };
    }, [subsections]);

    return activeId;
};

export const useReportUnsavedSettings = (isDirty: boolean) => {
    const report = useContext(UnsavedSettingsContext);
    const id = useId();

    useEffect(() => {
        if (!report) {
            return;
        }
        report(id, isDirty);
        return () => report(id, false);
    }, [report, id, isDirty]);
};

const scrollMainToTop = () => document.getElementById('app-manage-main')?.scrollTo({top: 0});

export const SectionedSettings = ({navLabel, basePath, groups, isReady}: SectionedSettingsProps) => {
    const {section: sectionSlug} = useParams();
    const location = useLocation();
    const navigate = useNavigate();
    const dirtyForms = useRef(new Set<string>());
    const [registeredSubsections, setRegisteredSubsections] = useState<SettingsSubsection[]>([]);
    const scrolledToHash = useRef<string | null>(null);

    const registerSubsection = useCallback((subsection: SettingsSubsection) => {
        setRegisteredSubsections(current => [
            ...current.filter(existing => existing.id !== subsection.id),
            subsection,
        ].sort(bySubsectionDocumentOrder));
        return () => setRegisteredSubsections(current => current.filter(existing => existing.id !== subsection.id));
    }, []);

    const subsections = registeredSubsections.length > 1 ? registeredSubsections : NO_SUBSECTIONS;
    const activeSubsectionId = useActiveSubsection(subsections);
    const hashId = decodeURIComponent(location.hash.replace(/^#/, ''));

    useEffect(() => {
        if (!hashId || scrolledToHash.current === location.key) {
            return;
        }
        const target = document.getElementById(hashId);
        if (target && registeredSubsections.some(subsection => subsection.id === hashId)) {
            scrolledToHash.current = location.key;
            target.scrollIntoView({block: 'start'});
        }
    }, [hashId, location.key, registeredSubsections]);

    const goToSubsection = (id: string) => {
        scrolledToHash.current = null;
        navigate({pathname: location.pathname, search: location.search, hash: id}, {replace: true});
        document.getElementById(id)?.scrollIntoView({behavior: 'smooth', block: 'start'});
    };

    const handleSubsectionClick = (event: MouseEvent<HTMLAnchorElement>, id: string) => {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
            return;
        }
        event.preventDefault();
        goToSubsection(id);
    };

    const reportUnsavedSettings = useCallback((id: string, isDirty: boolean) => {
        if (isDirty) {
            dirtyForms.current.add(id);
        } else {
            dirtyForms.current.delete(id);
        }
    }, []);

    useUnsavedChangesGuard(
        () => dirtyForms.current.size > 0,
        t`Leave without saving? Your changes to these settings will be lost.`,
    );

    const visibleGroups = groups.filter(group => group.sections.length > 0);
    const sections = visibleGroups.flatMap(group => group.sections);
    const activeSection = sections.find(section => section.slug === sectionSlug);
    const sectionPath = (section: SettingsSection) => `${basePath}/${section.slug}`;

    if (!activeSection && isReady && sections.length > 0) {
        const anchorId = sectionSlug ? '' : location.hash.replace(/^#/, '').split('?')[0];
        const target = sections.find(section => section.anchorId === anchorId) ?? sections[0];
        return <Navigate to={sectionPath(target)} replace/>;
    }

    const ActiveComponent = activeSection?.component;

    return (
        <UnsavedSettingsContext.Provider value={reportUnsavedSettings}>
            <SettingsSubsectionContext.Provider value={registerSubsection}>
                <PageBody>
                    <div className={classes.layout}>
                        <nav className={classes.nav} aria-label={navLabel}>
                            {visibleGroups.map(group => (
                                <div key={group.label} className={classes.group}>
                                    <div className={classes.groupLabel}>{group.label}</div>
                                    {group.sections.map(section => (
                                        <div key={section.slug}>
                                            <NavLink
                                                to={sectionPath(section)}
                                                onClick={scrollMainToTop}
                                                className={({isActive}) => classNames(classes.link, {
                                                    [classes.active]: isActive,
                                                    [classes.danger]: section.tone === 'danger',
                                                })}
                                            >
                                                <section.icon size={16} stroke={1.5}/>
                                                <span>{section.label}</span>
                                            </NavLink>
                                            {section === activeSection && subsections.length > 0 && (
                                                <ul className={classes.subsections}>
                                                    {subsections.map(subsection => (
                                                        <li key={subsection.id}>
                                                            <a
                                                                href={`#${subsection.id}`}
                                                                onClick={(event) => handleSubsectionClick(event, subsection.id)}
                                                                aria-current={subsection.id === activeSubsectionId ? 'location' : undefined}
                                                                className={classNames(classes.subsectionLink, {
                                                                    [classes.subsectionActive]: subsection.id === activeSubsectionId,
                                                                })}
                                                            >
                                                                {subsection.label}
                                                            </a>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            ))}
                        </nav>

                        <Select
                            className={classes.picker}
                            aria-label={t`Settings section`}
                            allowDeselect={false}
                            value={activeSection?.slug ?? null}
                            data={visibleGroups.map(group => ({
                                group: group.label,
                                items: group.sections.flatMap(section => [
                                    {value: section.slug, label: section.label},
                                    ...(section === activeSection
                                        ? subsections.map(subsection => ({value: `#${subsection.id}`, label: subsection.label}))
                                        : []),
                                ]),
                            }))}
                            renderOption={({option}) => (
                                <span className={classNames({[classes.pickerSubsection]: option.value.startsWith('#')})}>
                                    {option.label}
                                </span>
                            )}
                            onChange={(value) => {
                                if (!value) {
                                    return;
                                }
                                if (value.startsWith('#')) {
                                    goToSubsection(value.slice(1));
                                    return;
                                }
                                navigate(`${basePath}/${value}`);
                                scrollMainToTop();
                            }}
                        />

                        <div className={classes.content}>
                            {activeSection && ActiveComponent && (
                                <div id={activeSection.anchorId} key={activeSection.slug}>
                                    <ActiveComponent/>
                                </div>
                            )}
                        </div>
                    </div>
                </PageBody>
            </SettingsSubsectionContext.Provider>
        </UnsavedSettingsContext.Provider>
    );
};
