import {ReactNode} from 'react';
import {t} from '@lingui/macro';
import {ActionIcon, CopyButton, Tooltip, UnstyledButton} from '@mantine/core';
import {IconCheck, IconCopy} from '@tabler/icons-react';
import {Link} from 'react-router';
import classNames from 'classnames';
import classes from './cells.module.scss';

interface CellLinkBaseProps {
    children: ReactNode;
    title?: string;
    mono?: boolean;
    strong?: boolean;
}

type CellLinkProps = CellLinkBaseProps & ({ onClick: () => void } | { to: string });

export const CellLink = ({children, title, mono, strong, ...target}: CellLinkProps) => {
    const className = classNames(classes.link, mono && classes.mono, strong && classes.strong);

    if ('to' in target) {
        return (
            <Link className={className} title={title} to={target.to} onClick={event => event.stopPropagation()}>
                {children}
            </Link>
        );
    }

    return (
        <UnstyledButton
            className={className}
            title={title}
            onClick={event => {
                event.stopPropagation();
                target.onClick();
            }}
        >
            {children}
        </UnstyledButton>
    );
};

interface CellTextProps {
    children: ReactNode;
    title?: string;
    muted?: boolean;
    mono?: boolean;
    strong?: boolean;
    maxWidth?: number;
}

export const CellText = ({children, title, muted, mono, strong, maxWidth}: CellTextProps) => (
    <span
        className={classNames(classes.text, muted && classes.muted, mono && classes.mono, strong && classes.strong)}
        style={maxWidth ? {maxWidth} : undefined}
        title={title}
    >
        {children}
    </span>
);

interface IdentityCellProps {
    avatar?: ReactNode;
    primary: ReactNode;
    secondary?: ReactNode;
    trailing?: ReactNode;
    stacked?: boolean;
}

export const IdentityCell = ({avatar, primary, secondary, trailing, stacked}: IdentityCellProps) => (
    <span className={classes.identity}>
        {avatar}
        {stacked ? (
            <span className={classes.identityStack}>
                <span className={classes.identityPrimary}>{primary}</span>
                <span className={classes.identitySecondary}>{secondary}</span>
            </span>
        ) : (
            <>
                <span className={classes.identityPrimary}>{primary}</span>
                {secondary && <span className={classes.identitySecondary}>{secondary}</span>}
            </>
        )}
        {trailing}
    </span>
);

export const CellCopy = ({value, children}: { value: string, children: ReactNode }) => (
    <span className={classes.copy}>
        {children}
        <CopyButton value={value} timeout={1500}>
            {({copied, copy}) => (
                <Tooltip label={copied ? t`Copied` : t`Copy`} withArrow openDelay={200}>
                    <ActionIcon
                        className={classes.copyButton}
                        data-copied={copied || undefined}
                        variant="subtle"
                        color={copied ? 'teal' : 'gray'}
                        size="sm"
                        aria-label={t`Copy`}
                        onClick={event => {
                            event.stopPropagation();
                            copy();
                        }}
                    >
                        {copied ? <IconCheck size={14}/> : <IconCopy size={14}/>}
                    </ActionIcon>
                </Tooltip>
            )}
        </CopyButton>
    </span>
);

export const EmptyCell = () => <span className={classes.muted} aria-hidden>–</span>;
