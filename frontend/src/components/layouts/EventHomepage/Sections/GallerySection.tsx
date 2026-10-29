import {useCallback, useEffect, useState} from "react";
import {t} from "@lingui/macro";
import {Modal} from "@mantine/core";
import {IconChevronLeft, IconChevronRight, IconX} from "@tabler/icons-react";
import {HomepageSection, Image} from "../../../../types.ts";
import {HomepageSectionClasses} from "../Lineup";
import {SectionShell} from "./SectionShell.tsx";
import {responsiveImage} from "../../../../utilites/imageTransform.ts";
import classes from "./Sections.module.scss";

const MAX_TILES = 5;

export const GallerySection = ({images: availableImages, section, sectionClasses}: {
    images: Image[],
    section: HomepageSection,
    sectionClasses: HomepageSectionClasses,
}) => {
    const [lightbox, setLightbox] = useState({index: 0, opened: false});
    const images = (section.image_ids ?? [])
        .map(id => availableImages.find(image => Number(image.id) === id))
        .filter((image): image is Image => !!image);

    if (images.length === 0) {
        return null;
    }

    const tiles = images.slice(0, MAX_TILES);
    const hiddenCount = images.length - tiles.length;
    const totalCount = images.length;

    return (
        <SectionShell section={section} sectionClasses={sectionClasses}>
            <div className={classes.gallery} data-count={tiles.length}>
                {tiles.map((image, index) => {
                    const photoNumber = index + 1;
                    const showsMore = hiddenCount > 0 && index === tiles.length - 1;
                    return (
                        <button
                            key={image.id}
                            type="button"
                            className={classes.galleryItem}
                            style={{backgroundColor: image.avg_colour ?? undefined}}
                            onClick={() => setLightbox({index, opened: true})}
                            aria-label={showsMore ? t`View all ${totalCount} photos` : t`Open photo ${photoNumber}`}
                        >
                            <img {...responsiveImage(image.url, '(min-width: 768px) 340px, 50vw')} alt="" loading="lazy" decoding="async"/>
                            {showsMore && <span className={classes.galleryMore} aria-hidden="true">+{hiddenCount}</span>}
                        </button>
                    );
                })}
            </div>
            <Lightbox
                images={images}
                index={Math.min(lightbox.index, images.length - 1)}
                opened={lightbox.opened}
                onChange={index => setLightbox({index, opened: true})}
                onClose={() => setLightbox(current => ({...current, opened: false}))}
            />
        </SectionShell>
    );
};

const Lightbox = ({images, index, opened, onChange, onClose}: {
    images: Image[],
    index: number,
    opened: boolean,
    onChange: (index: number) => void,
    onClose: () => void,
}) => {
    const [touchStartX, setTouchStartX] = useState<number | null>(null);
    const step = useCallback(
        (delta: number) => onChange((index + delta + images.length) % images.length),
        [index, images.length, onChange],
    );

    useEffect(() => {
        if (!opened) {
            return;
        }
        const onKey = (keyEvent: KeyboardEvent) => {
            if (keyEvent.key === 'ArrowRight') {
                step(1);
            }
            if (keyEvent.key === 'ArrowLeft') {
                step(-1);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [step, opened]);

    const image = images[index];
    const position = `${index + 1} / ${images.length}`;

    return (
        <Modal
            opened={opened}
            onClose={onClose}
            fullScreen
            withCloseButton={false}
            padding={0}
            classNames={{content: classes.lightbox, body: classes.lightboxBody}}
            title={t`Photos`}
            styles={{header: {display: 'none'}}}
        >
            <img
                src={image.url}
                alt=""
                className={classes.lightboxImage}
                onTouchStart={touchEvent => setTouchStartX(touchEvent.touches[0].clientX)}
                onTouchEnd={touchEvent => {
                    if (touchStartX === null) {
                        return;
                    }
                    const distance = touchEvent.changedTouches[0].clientX - touchStartX;
                    if (Math.abs(distance) > 40) {
                        step(distance < 0 ? 1 : -1);
                    }
                    setTouchStartX(null);
                }}
            />
            <button type="button" className={classes.lightboxClose} onClick={onClose} aria-label={t`Close`}><IconX size={20}/></button>
            {images.length > 1 && (
                <>
                    <button type="button" className={classes.lightboxPrev} onClick={() => step(-1)} aria-label={t`Previous photo`}>
                        <IconChevronLeft size={24}/>
                    </button>
                    <button type="button" className={classes.lightboxNext} onClick={() => step(1)} aria-label={t`Next photo`}>
                        <IconChevronRight size={24}/>
                    </button>
                    <span className={classes.lightboxCount}>{position}</span>
                </>
            )}
        </Modal>
    );
};
