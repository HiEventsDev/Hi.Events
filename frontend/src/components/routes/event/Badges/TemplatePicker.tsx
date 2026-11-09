import {KeyboardEvent} from "react";
import {t} from "@lingui/macro";
import {Badge as BadgeData, BadgeDesign, BadgeTemplate} from "../../../../types.ts";
import {BADGE_TEMPLATES, BadgeImages, getBadgeTemplateDescription, getBadgeTemplateLabel} from "../../../../utilites/badges.ts";
import {ScaledNameBadge} from "../../../common/NameBadge/ScaledNameBadge.tsx";
import classes from "./BadgeDesigner.module.scss";

interface TemplatePickerProps {
    design: BadgeDesign;
    badge: BadgeData;
    eventName: string;
    images: BadgeImages;
    onSelect: (template: BadgeTemplate) => void;
}

const ARROW_STEPS: Record<string, number> = {ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1};

export const TemplatePicker = ({design, badge, eventName, images, onSelect}: TemplatePickerProps) => {
    const handleKeyDown = (event: KeyboardEvent<HTMLButtonElement>, index: number) => {
        const step = ARROW_STEPS[event.key];
        if (!step) {
            return;
        }
        event.preventDefault();
        const next = (index + step + BADGE_TEMPLATES.length) % BADGE_TEMPLATES.length;
        onSelect(BADGE_TEMPLATES[next]);
        (event.currentTarget.parentElement?.children[next] as HTMLElement | undefined)?.focus();
    };

    return (
        <div className={classes.templateGrid} role="radiogroup" aria-label={t`Template`}>
            {BADGE_TEMPLATES.map((template, index) => {
                const selected = design.template === template;
                return (
                    <button
                        key={template}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        aria-label={getBadgeTemplateLabel(template)}
                        tabIndex={selected ? 0 : -1}
                        className={classes.templateOption}
                        data-selected={selected || undefined}
                        title={getBadgeTemplateDescription(template)}
                        onClick={() => onSelect(template)}
                        onKeyDown={(event) => handleKeyDown(event, index)}
                        data-testid={`badge-template-${template.toLowerCase()}`}
                    >
                        <div className={classes.templateThumb} aria-hidden="true">
                            <ScaledNameBadge
                                layout={selected
                                    ? {...design, back_mode: 'NONE'}
                                    : {...design, template, back_mode: 'NONE', background_color: null, text_color: null}}
                                badge={badge}
                                eventName={eventName}
                                images={images}
                                maxScale={1}
                            />
                        </div>
                        <span className={classes.templateLabel} aria-hidden="true">{getBadgeTemplateLabel(template)}</span>
                    </button>
                );
            })}
        </div>
    );
};
