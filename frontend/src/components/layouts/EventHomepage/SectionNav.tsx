import {MouseEvent, useEffect, useRef, useState} from "react";
import {t} from "@lingui/macro";
import {useMediaQuery} from "@mantine/hooks";
import {WIDE_LAYOUT_QUERY} from "./hooks.ts";
import {scrollBehavior} from "../../../utilites/motion.ts";
import {sizedImageUrl} from "../../../utilites/imageTransform.ts";
import classes from "./EventHomepage.module.scss";

interface NavItem {
    id: string;
    label: string;
}

interface SectionNavProps {
    container: HTMLElement | null;
    title: string;
    subtitle?: string | null;
    thumbnail?: string;
}

const ACTIVE_LINE = 140;
const SECTION_SELECTOR = ':scope > section[id]';

const sameItems = (a: NavItem[], b: NavItem[]) =>
    a.length === b.length && a.every((item, index) => item.id === b[index].id && item.label === b[index].label);

export const SectionNav = ({container, title, subtitle, thumbnail}: SectionNavProps) => {
    const [items, setItems] = useState<NavItem[]>([]);
    const [visible, setVisible] = useState(false);
    const [activeId, setActiveId] = useState<string | null>(null);
    const [overflowing, setOverflowing] = useState(false);
    const linksRef = useRef<HTMLDivElement>(null);
    const wide = useMediaQuery(WIDE_LAYOUT_QUERY);
    const scope = wide ? container : null;

    useEffect(() => {
        if (!scope) {
            setItems([]);
            return;
        }
        let frame = 0;
        const collect = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => {
                const next = Array.from(scope.querySelectorAll<HTMLElement>(SECTION_SELECTOR))
                    .map(section => ({id: section.id, label: section.querySelector('h2')?.textContent?.trim() ?? ''}))
                    .filter(item => item.label);
                setItems(current => sameItems(current, next) ? current : next);
            });
        };
        collect();
        const observer = new MutationObserver(collect);
        observer.observe(scope, {childList: true, subtree: true, characterData: true});

        return () => {
            observer.disconnect();
            cancelAnimationFrame(frame);
        };
    }, [scope]);

    useEffect(() => {
        const heading = scope?.querySelector('h1');
        if (!heading) {
            return;
        }
        const observer = new IntersectionObserver(([entry]) => {
            setVisible(!entry.isIntersecting && entry.boundingClientRect.top < 0);
        });
        observer.observe(heading);

        return () => observer.disconnect();
    }, [scope]);

    useEffect(() => {
        if (!visible || items.length === 0) {
            return;
        }
        let frame = 0;
        const update = () => {
            frame = 0;
            const passed = items.filter(item => (document.getElementById(item.id)?.getBoundingClientRect().top ?? Infinity) <= ACTIVE_LINE);
            setActiveId(passed[passed.length - 1]?.id ?? null);
        };
        const onScroll = () => {
            if (!frame) {
                frame = requestAnimationFrame(update);
            }
        };
        update();
        window.addEventListener('scroll', onScroll, {passive: true});

        return () => {
            window.removeEventListener('scroll', onScroll);
            cancelAnimationFrame(frame);
        };
    }, [visible, items]);

    useEffect(() => {
        const links = linksRef.current;
        if (!links) {
            return;
        }
        const measure = () => setOverflowing(links.scrollWidth > links.clientWidth + 1);
        const observer = new ResizeObserver(measure);
        observer.observe(links);
        measure();

        return () => observer.disconnect();
    }, [items]);

    useEffect(() => {
        const links = linksRef.current;
        const active = links?.querySelector<HTMLElement>('[data-active]');
        if (!links || !active) {
            return;
        }
        const start = active.offsetLeft - links.offsetLeft;
        if (start < links.scrollLeft || start + active.offsetWidth > links.scrollLeft + links.clientWidth) {
            links.scrollTo({left: start - 24, behavior: scrollBehavior()});
        }
    }, [activeId]);

    if (items.length < 2) {
        return null;
    }

    const jumpTo = (clickEvent: MouseEvent<HTMLAnchorElement>, id: string) => {
        clickEvent.preventDefault();
        const target = document.getElementById(id);
        if (!target) {
            return;
        }
        target.scrollIntoView({behavior: scrollBehavior(), block: 'start'});
        target.setAttribute('tabindex', '-1');
        target.focus({preventScroll: true});
    };

    return (
        <nav className={classes.sectionNav} data-visible={visible || undefined} aria-label={t`Sections`} inert={!visible}>
            <div className={classes.sectionNavInner}>
                <div className={classes.sectionNavIdentity}>
                    {thumbnail && <img src={sizedImageUrl(thumbnail, 160)} alt="" className={classes.sectionNavThumb}/>}
                    <div className={classes.sectionNavText}>
                        <span className={classes.sectionNavTitle}>{title}</span>
                        {subtitle && <span className={classes.sectionNavSubtitle}>{subtitle}</span>}
                    </div>
                </div>
                <div className={classes.sectionNavLinks} ref={linksRef} data-overflowing={overflowing || undefined}>
                    {items.map(item => (
                        <a
                            key={item.id}
                            href={`#${item.id}`}
                            className={classes.sectionNavLink}
                            data-active={item.id === activeId || undefined}
                            aria-current={item.id === activeId ? 'location' : undefined}
                            onClick={clickEvent => jumpTo(clickEvent, item.id)}
                        >
                            {item.label}
                        </a>
                    ))}
                </div>
            </div>
        </nav>
    );
};
