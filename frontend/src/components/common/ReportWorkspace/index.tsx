import {ComponentType, useCallback, useEffect, useRef, useState} from "react";
import {Navigate, NavLink, useParams, useSearchParams} from "react-router";
import {VisuallyHidden} from "@mantine/core";
import {t} from "@lingui/macro";
import classNames from "classnames";
import {PageBody} from "../PageBody";
import {PageTitle} from "../PageTitle";
import {SHARED_REPORT_PARAMS} from "../../../hooks/useReportFilters.ts";
import classes from "./ReportWorkspace.module.scss";

export interface ReportDefinition {
    id: string;
    label: string;
    description: string;
    icon: ComponentType<{ size?: number; stroke?: number }>;
    component: ComponentType<{ description: string }>;
}

interface ReportWorkspaceProps {
    basePath: string;
    reports: ReportDefinition[];
    isReady: boolean;
}

export const ReportWorkspace = ({basePath, reports, isReady}: ReportWorkspaceProps) => {
    const {reportType} = useParams();
    const [searchParams] = useSearchParams();
    const activeReport = reports.find(report => report.id === reportType);

    const tabsRef = useRef<HTMLElement>(null);
    const [tabOverflow, setTabOverflow] = useState({start: false, end: false});
    const updateTabOverflow = useCallback(() => {
        const tabs = tabsRef.current;
        if (tabs) {
            setTabOverflow({
                start: tabs.scrollLeft > 1,
                end: tabs.scrollWidth - tabs.clientWidth - tabs.scrollLeft > 1,
            });
        }
    }, []);

    useEffect(() => {
        const tabs = tabsRef.current;
        if (!tabs) {
            return;
        }
        updateTabOverflow();
        const observer = new ResizeObserver(updateTabOverflow);
        observer.observe(tabs);
        return () => observer.disconnect();
    }, [updateTabOverflow, reports.length]);

    useEffect(() => {
        const tabs = tabsRef.current;
        const activeTab = tabs?.querySelector('[aria-current="page"]');
        if (tabs && activeTab) {
            const offset = activeTab.getBoundingClientRect().left - tabs.getBoundingClientRect().left;
            tabs.scrollLeft += offset - (tabs.clientWidth - activeTab.clientWidth) / 2;
        }
    }, [reportType, reports.length]);

    if (!activeReport && isReady && reports.length > 0) {
        return <Navigate to={`${basePath}/report/${reports[0].id}`} replace/>;
    }

    const sharedSearch = new URLSearchParams(
        SHARED_REPORT_PARAMS.flatMap(key => searchParams.has(key) ? [[key, searchParams.get(key) as string]] : []),
    ).toString();
    const ActiveComponent = activeReport?.component;

    return (
        <PageBody>
            <PageTitle>{t`Reports`}</PageTitle>

            <div className={classes.sheet}>
                <nav
                    ref={tabsRef}
                    className={classes.tabs}
                    aria-label={t`Reports`}
                    data-overflow-start={tabOverflow.start || undefined}
                    data-overflow-end={tabOverflow.end || undefined}
                    onScroll={updateTabOverflow}
                >
                    {reports.map(report => (
                        <NavLink
                            key={report.id}
                            to={{pathname: `${basePath}/report/${report.id}`, search: sharedSearch}}
                            className={({isActive}) => classNames(classes.tab, isActive && classes.active)}
                            data-testid={`report-tab-${report.id}`}
                        >
                            <report.icon size={16} stroke={1.6}/>
                            {report.label}
                        </NavLink>
                    ))}
                </nav>

                {activeReport && ActiveComponent && (
                    <>
                        <VisuallyHidden component="h2" id="report-heading">{activeReport.label}</VisuallyHidden>
                        <section key={activeReport.id} className={classes.body} aria-labelledby="report-heading">
                            <ActiveComponent description={activeReport.description}/>
                        </section>
                    </>
                )}
            </div>
        </PageBody>
    );
};
