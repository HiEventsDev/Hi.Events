import {Anchor, Button, Skeleton, UnstyledButton} from "@mantine/core";
import {IconCheck} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {NavLink} from "react-router";
import classNames from "classnames";
import {Event, ShareCaption, ShareCaptionTone} from "../../../types.ts";
import {useGetEventShareSuggestions} from "../../../queries/useGetEventShareSuggestions.ts";
import {useGenerateEventShareSuggestions} from "../../../mutations/useGenerateEventShareSuggestions.ts";
import {useAccess} from "../../../hooks/useAccess.ts";
import {showError} from "../../../utilites/notifications.tsx";
import classes from "./ShareModal.module.scss";

interface CaptionSuggestionsProps {
    event: Event;
    caption: string;
    onPick: (caption: string) => void;
}

const toneLabel = (tone: ShareCaptionTone) => ({
    FRIENDLY: t`Friendly`,
    PROFESSIONAL: t`Professional`,
    ENERGETIC: t`Energetic`,
    SHORT: t`Short`,
}[tone]);

export const CaptionSuggestions = ({event, caption, onPick}: CaptionSuggestionsProps) => {
    const {isReadOnly, can} = useAccess();
    const {data: suggestions} = useGetEventShareSuggestions(event.id, true);
    const generateMutation = useGenerateEventShareSuggestions();

    if (!suggestions) {
        return null;
    }

    if (suggestions.availability === 'PAYMENTS_NOT_CONNECTED') {
        return (
            <p className={classes.suggestionsHint} data-testid="share-suggestions-connect-hint">
                {t`Caption suggestions are available once you connect Stripe.`}
                {can('MANAGE_PAYMENTS') && (
                    <>
                        {' '}
                        <Anchor component={NavLink} to={`/manage/organizer/${event.organizer_id}/settings/payouts`} size="sm">
                            {t`Set up payouts`}
                        </Anchor>
                    </>
                )}
            </p>
        );
    }

    if (suggestions.availability !== 'AVAILABLE') {
        return null;
    }

    const captions: ShareCaption[] = suggestions.captions;
    const canGenerate = !isReadOnly && suggestions.remaining_generations > 0;

    const generate = () => generateMutation.mutate({eventId: event.id}, {
        onError: (error: any) => showError(
            error?.response?.data?.message || t`We couldn't suggest captions right now. Please try again.`
        ),
    });

    if (isReadOnly && captions.length === 0) {
        return null;
    }

    return (
        <div className={classes.suggestions}>
            <div className={classes.suggestionsHeader}>
                <span className={classes.sectionLabel}>{t`Suggestions`}</span>
                {!isReadOnly && (
                    <span className={classes.suggestionsActions}>
                        <span className={classes.suggestionsQuota}>
                            {suggestions.remaining_generations === 0
                                ? t`None left today`
                                : t`${suggestions.remaining_generations} left today`}
                        </span>
                        <Button
                            size="compact-sm"
                            variant="default"
                            onClick={generate}
                            loading={generateMutation.isPending}
                            disabled={!canGenerate}
                            leftSection={<span aria-hidden>✨</span>}
                            styles={{section: {marginInlineEnd: 4}}}
                            data-testid="share-suggest-captions-button"
                        >
                            {captions.length === 0 ? t`Suggest captions` : t`Suggest new ones`}
                        </Button>
                    </span>
                )}
            </div>

            {generateMutation.isPending && (
                <div className={classes.suggestionList} aria-hidden>
                    {[92, 78, 85, 60].map((width) => (
                        <Skeleton key={width} height={14} width={`${width}%`} radius="sm" animate={false}/>
                    ))}
                </div>
            )}

            {!generateMutation.isPending && captions.length > 0 && (
                <ul className={classes.suggestionList}>
                    {captions.map((suggestion) => {
                        const isPicked = suggestion.text === caption;

                        return (
                            <li key={suggestion.tone}>
                                <UnstyledButton
                                    className={classNames(classes.suggestion, isPicked && classes.suggestionPicked)}
                                    onClick={() => onPick(suggestion.text)}
                                    aria-pressed={isPicked}
                                    title={suggestion.text}
                                    data-testid={`share-suggestion-${suggestion.tone.toLowerCase()}`}
                                >
                                    <span className={classes.suggestionTone}>
                                        {toneLabel(suggestion.tone)}
                                        {isPicked && <IconCheck size={13} stroke={2.5}/>}
                                    </span>
                                    <span className={classes.suggestionText}>{suggestion.text}</span>
                                </UnstyledButton>
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
};
