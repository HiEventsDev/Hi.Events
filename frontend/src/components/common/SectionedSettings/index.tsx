import {ComponentType, createContext, useCallback, useContext, useEffect, useId, useRef} from "react";
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

const UnsavedSettingsContext = createContext<((id: string, isDirty: boolean) => void) | null>(null);

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
            <PageBody>
                <div className={classes.layout}>
                    <nav className={classes.nav} aria-label={navLabel}>
                        {visibleGroups.map(group => (
                            <div key={group.label} className={classes.group}>
                                <div className={classes.groupLabel}>{group.label}</div>
                                {group.sections.map(section => (
                                    <NavLink
                                        key={section.slug}
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
                            items: group.sections.map(section => ({value: section.slug, label: section.label})),
                        }))}
                        onChange={(slug) => {
                            if (slug) {
                                navigate(`${basePath}/${slug}`);
                                scrollMainToTop();
                            }
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
        </UnsavedSettingsContext.Provider>
    );
};
