import {t} from "@lingui/macro";
import {HomepageSection, Image} from "../../../../types.ts";
import {HomepageSectionClasses} from "../Lineup";
import {SectionShell} from "./SectionShell.tsx";
import {sizedImageUrl} from "../../../../utilites/imageTransform.ts";
import classes from "./Sections.module.scss";

export const SponsorsSection = ({images: availableImages, section, sectionClasses}: {
    images: Image[],
    section: HomepageSection,
    sectionClasses: HomepageSectionClasses,
}) => {
    const sponsors = (section.sponsors ?? [])
        .map(sponsor => ({sponsor, image: availableImages.find(image => Number(image.id) === sponsor.image_id)}))
        .filter((item): item is { sponsor: typeof item.sponsor, image: Image } => !!item.image);

    if (sponsors.length === 0) {
        return null;
    }

    return (
        <SectionShell section={section} sectionClasses={sectionClasses} fallbackHeading={t`Sponsors`}>
            <div className={classes.sponsors}>
                {sponsors.map(({sponsor, image}) => {
                    const logo = <img src={sizedImageUrl(image.url, 400)} alt={sponsor.name ?? ''} loading="lazy"/>;

                    return sponsor.url
                        ? (
                            <a key={sponsor.image_id} href={sponsor.url} target="_blank" rel="noopener noreferrer nofollow" className={classes.sponsor} title={sponsor.name ?? undefined}>
                                {logo}
                            </a>
                        )
                        : <span key={sponsor.image_id} className={classes.sponsor} title={sponsor.name ?? undefined}>{logo}</span>;
                })}
            </div>
        </SectionShell>
    );
};
