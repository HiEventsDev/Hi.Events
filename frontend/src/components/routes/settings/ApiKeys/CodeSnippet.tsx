import {useEffect} from "react";
import {ActionIcon, Tooltip} from "@mantine/core";
import {useClipboard} from "@mantine/hooks";
import {t} from "@lingui/macro";
import {IconCheck, IconCopy} from "@tabler/icons-react";
import classes from "./ApiKeys.module.scss";

export const CodeSnippet = ({code, onCopy}: { code: string, onCopy?: () => void }) => {
    const clipboard = useClipboard({timeout: 2000});

    useEffect(() => {
        if (clipboard.copied) {
            onCopy?.();
        }
    }, [clipboard.copied]);

    return (
        <div className={classes.snippet}>
            <pre className={classes.snippetCode} onCopy={onCopy}>{code}</pre>
            <Tooltip label={clipboard.copied ? t`Copied` : t`Copy`} withArrow>
                <ActionIcon
                    className={classes.snippetCopy}
                    variant="subtle"
                    color={clipboard.copied ? 'teal' : 'gray'}
                    aria-label={t`Copy command`}
                    onClick={() => clipboard.copy(code)}
                >
                    {clipboard.copied ? <IconCheck size={16}/> : <IconCopy size={16}/>}
                </ActionIcon>
            </Tooltip>
        </div>
    );
};
