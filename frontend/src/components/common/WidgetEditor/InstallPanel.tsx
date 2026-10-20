import {Button, CopyButton as MantineCopyButton, Select, Stack, Text, Tooltip} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconCheck, IconCode, IconSparkles} from "@tabler/icons-react";
import {DesignerSection} from "../DesignerShell";
import {WidgetType} from "../../../utilites/widgetConfig.ts";
import {
    agentInstructions,
    combinedSnippet,
    htmlScriptTag,
    htmlWidgetTag,
    reactComponent,
    reactUsage,
    trackingSnippet,
} from "./embedCode.ts";
import {InstallPlatform, installGuides} from "./installGuides.ts";
import {Snippet} from "./Snippet.tsx";
import classes from './WidgetEditor.module.scss';

interface InstallPanelProps {
    type: WidgetType;
    attributes: [string, string][];
    scriptUrl: string;
    platform: InstallPlatform;
    onPlatformChange: (platform: InstallPlatform) => void;
    copyBlocked: boolean;
}

export const InstallPanel = ({type, attributes, scriptUrl, platform, onPlatformChange, copyBlocked}: InstallPanelProps) => {
    const guides = installGuides();
    const guide = guides.find(candidate => candidate.value === platform) ?? guides[0];
    const scriptTag = htmlScriptTag(scriptUrl);
    const htmlTag = htmlWidgetTag(attributes);
    const reactCode = reactComponent(attributes, scriptUrl);

    return (
        <DesignerSection
            icon={<IconCode/>}
            title={t`Add to your site`}
            action={(
                <MantineCopyButton value={agentInstructions(type, attributes, scriptUrl)} timeout={2000}>
                    {({copied, copy}) => (
                        <Tooltip label={t`Copies step-by-step instructions to paste into ChatGPT, Claude, Cursor or your site builder's AI assistant`} multiline w={240} withArrow>
                            <Button
                                variant="subtle"
                                size="compact-xs"
                                color={copied ? 'teal' : undefined}
                                leftSection={copied ? <IconCheck size={14}/> : <IconSparkles size={14}/>}
                                onClick={copy}
                                disabled={copyBlocked}
                                data-testid="widget-copy-agent-instructions-button"
                            >
                                {copied ? t`Copied` : t`Copy for AI agent`}
                            </Button>
                        </Tooltip>
                    )}
                </MantineCopyButton>
            )}
        >
            <Select
                label={t`Where is your website?`}
                size="sm"
                mb="md"
                allowDeselect={false}
                value={platform}
                onChange={(value) => onPlatformChange((value ?? 'html') as InstallPlatform)}
                data={guides.map(guide => ({value: guide.value, label: guide.label}))}
                data-testid="widget-platform-select"
            />
            {guide.steps.length > 0 && (
                <ol className={classes.guideSteps}>
                    {guide.steps.map(step => <li key={step}>{step}</li>)}
                </ol>
            )}
            {guide.code === 'split' && (
                <Stack gap="md">
                    <Snippet copyable={!copyBlocked} step={1} label={t`Add this to your site's <head>`} code={scriptTag} wrap/>
                    <Snippet copyable={!copyBlocked} step={2} label={t`Paste this where the widget should appear`} code={htmlTag} testId="widget-embed-code"/>
                </Stack>
            )}
            {guide.code === 'combined' && (
                <Snippet copyable={!copyBlocked} label={t`Code to paste`} code={combinedSnippet(attributes, scriptUrl)} testId="widget-embed-code"/>
            )}
            {guide.code === 'react' && (
                <Stack gap="md">
                    <Snippet copyable={!copyBlocked} step={1} label={t`Save this as HiEventsWidget.jsx`} code={reactCode} testId="widget-embed-code"/>
                    <Snippet copyable={!copyBlocked} step={2} label={t`Render it where the widget should appear`} code={reactUsage()}/>
                </Stack>
            )}
            {guide.tip && <Text size="xs" c="dimmed" mt="sm">{guide.tip}</Text>}
            {type !== 'LINEUP' && type !== 'SCHEDULE' && (
                <details className={classes.tracking}>
                    <summary>{t`Track sales in your own analytics`}</summary>
                    <Text size="xs" c="dimmed" my={8}>
                        {t`When an order completes, the widget fires an hievents:order-completed event on your page with the order total and currency. This example sends it to Google Analytics and Meta.`}
                    </Text>
                    <Snippet copyable label={t`Add after the embed code`} code={trackingSnippet()}/>
                </details>
            )}
        </DesignerSection>
    );
};
