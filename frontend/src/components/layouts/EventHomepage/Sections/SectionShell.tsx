import {ReactNode} from "react";
import {HomepageSection} from "../../../../types.ts";
import {HomepageSectionClasses} from "../Lineup";
import classes from "./Sections.module.scss";

interface SectionShellProps {
    section: HomepageSection;
    sectionClasses: HomepageSectionClasses;
    fallbackHeading?: string;
    children: ReactNode;
}

export const SectionShell = ({section, sectionClasses, fallbackHeading, children}: SectionShellProps) => {
    const heading = section.heading || fallbackHeading;

    return (
        <section className={`${sectionClasses.section} ${classes.queryContainer}`} id={`section-${section.id}`} data-testid={`homepage-section-${section.type.toLowerCase()}`}>
            {heading && (
                <div className={sectionClasses.sectionHeader}>
                    <h2 className={sectionClasses.sectionTitle}>{heading}</h2>
                </div>
            )}
            {children}
        </section>
    );
};
