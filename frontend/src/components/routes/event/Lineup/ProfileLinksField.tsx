import {t} from "@lingui/macro";
import {ActionIcon, Button, TextInput} from "@mantine/core";
import {IconLink, IconPlus, IconX} from "@tabler/icons-react";
import {ProfileLink} from "../../../../api/lineup.client.ts";
import {ProfileLinkIcon} from "../../../common/ProfileLinkIcon";
import {MAX_LINKS} from "../../../../utilites/lineupImport.ts";
import classes from "./Lineup.module.scss";

interface ProfileLinksFieldProps {
    value: string[];
    savedLinks: ProfileLink[];
    onChange: (links: string[]) => void;
    errors: Record<string, unknown>;
    disabled?: boolean;
}

export const ProfileLinksField = ({value, savedLinks, onChange, errors, disabled}: ProfileLinksFieldProps) => {
    const typeFor = (url: string) => savedLinks.find(link => link.url === url.trim())?.type;

    return (
        <div className={classes.fieldStack}>
            <p className={classes.fieldHelp}>
                {t`Spotify, YouTube, SoundCloud, Instagram, LinkedIn, a website and more. Each link gets the right icon automatically.`}
            </p>
            {value.map((url, index) => {
                const type = typeFor(url);
                const linkNumber = index + 1;

                return (
                    <div key={index} className={classes.linkRow}>
                        <TextInput
                            className={classes.linkInput}
                            leftSection={type ? <ProfileLinkIcon type={type} size={16}/> : <IconLink size={16}/>}
                            placeholder="https://"
                            type="url"
                            value={url}
                            disabled={disabled}
                            error={errors[`links.${index}`] as string | undefined}
                            aria-label={t`Link ${linkNumber}`}
                            onChange={event => onChange(value.map((existing, i) => i === index ? event.currentTarget.value : existing))}
                        />
                        {!disabled && (
                            <ActionIcon
                                variant="subtle"
                                color="gray"
                                aria-label={t`Remove link ${linkNumber}`}
                                onClick={() => onChange(value.length === 1 ? [''] : value.filter((_, i) => i !== index))}
                            >
                                <IconX size={16}/>
                            </ActionIcon>
                        )}
                    </div>
                );
            })}
            {!disabled && value.length < MAX_LINKS && (
                <Button
                    variant="subtle"
                    size="xs"
                    leftSection={<IconPlus size={14}/>}
                    className={classes.addLinkButton}
                    onClick={() => onChange([...value, ''])}
                >
                    {t`Add link`}
                </Button>
            )}
        </div>
    );
};
