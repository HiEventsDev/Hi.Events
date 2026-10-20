import {HomepageSection} from "../../../../types.ts";
import {UserGeneratedContent} from "../../../common/UserGeneratedContent";
import {HomepageSectionClasses} from "../Lineup";
import {SectionShell} from "./SectionShell.tsx";
import classes from "./Sections.module.scss";

export const TextSection = ({section, sectionClasses}: { section: HomepageSection, sectionClasses: HomepageSectionClasses }) => {
    if (!section.body) {
        return null;
    }

    return (
        <SectionShell section={section} sectionClasses={sectionClasses}>
            <UserGeneratedContent className={classes.richText} html={section.body}/>
        </SectionShell>
    );
};
