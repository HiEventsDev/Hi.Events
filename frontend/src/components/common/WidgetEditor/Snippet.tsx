import {Text} from "@mantine/core";
import {CopyButton} from "../CopyButton";
import classes from './WidgetEditor.module.scss';

interface SnippetProps {
    step?: number;
    label: string;
    code: string;
    copyable: boolean;
    testId?: string;
    wrap?: boolean;
}

export const Snippet = ({step, label, code, copyable, testId, wrap}: SnippetProps) => (
    <div className={classes.snippet}>
        <div className={classes.snippetHeader}>
            {step && <span className={classes.step}>{step}</span>}
            <Text size="sm" fw={500} className={classes.snippetLabel}>{label}</Text>
            {copyable && <CopyButton value={code}/>}
        </div>
        <pre className={classes.code} data-wrap={wrap ? '' : undefined} data-testid={testId}><code>{code}</code></pre>
    </div>
);
