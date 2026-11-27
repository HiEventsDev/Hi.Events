import {t} from "@lingui/macro";
import {Button} from "@mantine/core";
import {CrmAutomationTemplate} from "../../../types.ts";
import {automationDescription, automationTitle} from "../../../utilites/labels.ts";
import {TriggerIcon} from "./TriggerIcon.tsx";
import classes from "./Automations.module.scss";

export const TriggerPicker = ({templates, onPick}: {
    templates: CrmAutomationTemplate[],
    onPick?: (template: CrmAutomationTemplate) => void,
}) => (
    <ul className={classes.picker}>
        {templates.map(template => (
            <li key={template.trigger} className={classes.pickerOption}>
                <span className={classes.pickerIcon}><TriggerIcon trigger={template.trigger}/></span>
                <span className={classes.pickerText}>
                    <strong>{automationTitle(template.trigger)}</strong>
                    <span>{automationDescription(template.trigger)}</span>
                </span>
                {onPick && (
                    <Button
                        variant="default"
                        size="sm"
                        onClick={() => onPick(template)}
                        data-testid={`automation-template-${template.trigger}`}
                    >
                        {t`Set up`}
                    </Button>
                )}
            </li>
        ))}
    </ul>
);
