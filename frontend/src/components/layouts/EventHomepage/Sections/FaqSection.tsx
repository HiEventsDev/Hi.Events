import {t} from "@lingui/macro";
import {IconPlus} from "@tabler/icons-react";
import {HomepageSection} from "../../../../types.ts";
import {UserGeneratedContent} from "../../../common/UserGeneratedContent";
import {HomepageSectionClasses} from "../Lineup";
import {SectionShell} from "./SectionShell.tsx";
import classes from "./Sections.module.scss";

export const FaqSection = ({section, sectionClasses}: { section: HomepageSection, sectionClasses: HomepageSectionClasses }) => {
    const faqs = section.faqs ?? [];

    if (faqs.length === 0) {
        return null;
    }

    return (
        <SectionShell section={section} sectionClasses={sectionClasses} fallbackHeading={t`Questions`}>
            <div className={classes.faqList}>
                {faqs.map((faq, index) => faq.answer
                    ? (
                        <details key={index} className={classes.faq}>
                            <summary>
                                <span>{faq.question}</span>
                                <IconPlus size={18} className={classes.faqIcon}/>
                            </summary>
                            <UserGeneratedContent className={classes.faqAnswer} html={faq.answer}/>
                        </details>
                    )
                    : (
                        <div key={index} className={classes.faq}>
                            <div className={classes.faqQuestion}>{faq.question}</div>
                        </div>
                    ))}
            </div>
        </SectionShell>
    );
};
