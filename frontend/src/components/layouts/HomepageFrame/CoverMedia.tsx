import {CSSProperties} from "react";
import {Image} from "../../../types.ts";
import {responsiveImage} from "../../../utilites/imageTransform.ts";
import classes from "./HomepageFrame.module.scss";

type CoverVariant = 'hero' | 'banner' | 'poster';

const BANNER_SIZES = '(min-width: 1320px) 1272px, 100vw';
const POSTER_SIZES = '(min-width: 768px) 420px, 100vw';

export const CoverMedia = ({image, alt, variant = 'hero'}: { image: Image, alt: string, variant?: CoverVariant }) => {
    const sized = !!(image.width && image.height);
    const style = {
        '--cover-ratio': sized ? `${image.width} / ${image.height}` : undefined,
        backgroundColor: image.avg_colour ?? undefined,
    } as CSSProperties;

    return (
        <div className={classes.cover} data-sized={sized || undefined} data-variant={variant} style={style}>
            <img src={image.lqip_base64 || image.url} alt="" aria-hidden="true" className={classes.coverBackdrop}/>
            <img
                {...responsiveImage(image.url, variant === 'poster' ? POSTER_SIZES : BANNER_SIZES)}
                alt={alt}
                className={classes.coverImage}
                fetchPriority="high"
            />
        </div>
    );
};
