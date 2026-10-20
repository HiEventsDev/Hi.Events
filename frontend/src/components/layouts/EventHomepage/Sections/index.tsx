import {HomepageSection, Image} from "../../../../types.ts";
import {HomepageSectionClasses} from "../Lineup";
import {TextSection} from "./TextSection.tsx";
import {FaqSection} from "./FaqSection.tsx";
import {VideoSection} from "./VideoSection.tsx";
import {GallerySection} from "./GallerySection.tsx";
import {SponsorsSection} from "./SponsorsSection.tsx";

interface CustomHomepageSectionProps {
    images: Image[];
    section: HomepageSection;
    sectionClasses: HomepageSectionClasses;
}

export const CustomHomepageSection = ({images, section, sectionClasses}: CustomHomepageSectionProps) => {
    switch (section.type) {
        case 'TEXT':
            return <TextSection section={section} sectionClasses={sectionClasses}/>;
        case 'FAQ':
            return <FaqSection section={section} sectionClasses={sectionClasses}/>;
        case 'VIDEO':
            return <VideoSection section={section} sectionClasses={sectionClasses}/>;
        case 'GALLERY':
            return <GallerySection images={images} section={section} sectionClasses={sectionClasses}/>;
        case 'SPONSORS':
            return <SponsorsSection images={images} section={section} sectionClasses={sectionClasses}/>;
        default:
            return null;
    }
};
