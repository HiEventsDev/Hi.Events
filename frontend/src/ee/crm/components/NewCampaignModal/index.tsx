import {useMemo, useState} from "react";
import {Link, useNavigate} from "react-router";
import {t} from "@lingui/macro";
import {Button, ScrollArea, SegmentedControl, Skeleton, TextInput, UnstyledButton} from "@mantine/core";
import {IconFile, IconSearch, IconSparkles, IconTemplate} from "@tabler/icons-react";
import {Modal, ModalFooter} from "../../../../components/common/Modal";
import {IdParam} from "../../../../types.ts";
import {useGetCrmCampaignDraftPreview} from "../../queries/useGetCrmCampaignDraftPreview.ts";
import {useGetCrmCampaignTemplates} from "../../queries/useGetCrmCampaignTemplates.ts";
import {FeatureFlag} from "../../../../constants/featureFlags.ts";
import {useLicensedFeature} from "../../../licensing/hooks/useLicensedFeature.ts";
import {useGetCrmSmsConnection} from "../../queries/useGetCrmSmsConnection.ts";
import classes from "./NewCampaignModal.module.scss";

type Choice = {
    id: string,
    group: 'blank' | 'starter' | 'template',
    label: string,
    hint: string,
    subject: string | null,
    preheader: string | null,
    body: string | null,
    query: string,
};

export const NewCampaignModal = ({organizerId, onClose}: {organizerId: IdParam, onClose: () => void}) => {
    const navigate = useNavigate();
    const templatesQuery = useGetCrmCampaignTemplates(organizerId);
    const [search, setSearch] = useState('');
    const [selectedId, setSelectedId] = useState('blank');
    const basePath = `/manage/organizer/${organizerId}/audience/campaigns`;
    const smsAvailable = useLicensedFeature(FeatureFlag.CRM_SMS).isEnabled;
    const smsConnectionQuery = useGetCrmSmsConnection(organizerId, smsAvailable);
    const [channel, setChannel] = useState<'EMAIL' | 'SMS'>('EMAIL');
    const isSmsReady = !!smsConnectionQuery.data;

    const choices = useMemo<Choice[]>(() => [
        {id: 'blank', group: 'blank', label: t`Blank campaign`, hint: t`Start from an empty message`, subject: null, preheader: null, body: null, query: ''},
        ...(templatesQuery.data?.starters ?? []).map((starter): Choice => ({
            id: `starter:${starter.key}`,
            group: 'starter',
            label: starter.name,
            hint: starter.description,
            subject: starter.subject,
            preheader: starter.preheader,
            body: starter.body,
            query: `?starter=${starter.key}`,
        })),
        ...(templatesQuery.data?.data ?? []).map((template): Choice => ({
            id: `template:${template.id}`,
            group: 'template',
            label: template.name,
            hint: template.subject ?? '',
            subject: template.subject,
            preheader: template.preheader,
            body: template.body,
            query: `?template=${template.id}`,
        })),
    ], [templatesQuery.data]);

    const term = search.trim().toLowerCase();
    const visible = choices.filter(choice => !term || choice.label.toLowerCase().includes(term) || choice.hint.toLowerCase().includes(term));
    const selected = visible.find(choice => choice.id === selectedId) ?? visible[0];
    const previewQuery = useGetCrmCampaignDraftPreview(organizerId, {
        subject: selected?.subject ?? null,
        preheader: selected?.preheader ?? null,
        body: selected?.body ?? null,
        event_id: null,
        early_access_product_ids: [],
        contact_id: null,
    }, !!selected?.body);

    const groups: {key: Choice['group'], label: string, icon: typeof IconFile}[] = [
        {key: 'blank', label: '', icon: IconFile},
        {key: 'starter', label: t`Starters`, icon: IconSparkles},
        {key: 'template', label: t`Your templates`, icon: IconTemplate},
    ];

    const start = (choice: Choice | undefined) => {
        if (!choice) {
            return;
        }
        navigate(`${basePath}/new${choice.query}`);
        onClose();
    };

    const move = (step: number) => {
        const index = visible.findIndex(choice => choice.id === selected?.id);
        const next = visible[Math.min(Math.max(index + step, 0), visible.length - 1)];
        if (next) {
            setSelectedId(next.id);
        }
    };

    const channelSwitch = smsAvailable && (
        <SegmentedControl
            className={classes.channel}
            fullWidth
            value={channel}
            onChange={value => setChannel(value === 'SMS' ? 'SMS' : 'EMAIL')}
            data={[{value: 'EMAIL', label: t`Email`}, {value: 'SMS', label: t`Text message`}]}
            data-testid="campaign-picker-channel"
        />
    );

    if (channel === 'SMS') {
        return (
            <Modal opened onClose={onClose} heading={t`New campaign`} size="lg">
                {channelSwitch}
                <div className={classes.smsChoice}>
                    {smsConnectionQuery.isLoading ? (
                        <Skeleton height={44} radius="sm"/>
                    ) : smsConnectionQuery.isError ? (
                        <p>{t`We couldn't check your text message setup. Please refresh the page.`}</p>
                    ) : isSmsReady ? (
                        <p>{t`A text message goes to everyone you choose who opted in to texts, in the countries you send to. Keep it short: your organizer name and an opt-out line are added automatically.`}</p>
                    ) : (
                        <p>
                            {t`Connect your Twilio account before sending text messages.`}{' '}
                            <Link to={`/manage/organizer/${organizerId}/settings#text-messages`} onClick={onClose}>{t`Set up text messages`}</Link>
                        </p>
                    )}
                </div>
                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button
                        disabled={!isSmsReady}
                        onClick={() => {
                            navigate(`${basePath}/new?channel=SMS`);
                            onClose();
                        }}
                        data-testid="campaign-picker-start-sms"
                    >
                        {t`Write a text message`}
                    </Button>
                </ModalFooter>
            </Modal>
        );
    }

    return (
        <Modal opened onClose={onClose} heading={t`New campaign`} size="xl">
            {channelSwitch}
            <div
                className={classes.picker}
                onKeyDown={event => {
                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        move(1);
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        move(-1);
                    } else if (event.key === 'Enter') {
                        event.preventDefault();
                        start(selected);
                    }
                }}
            >
                <div className={classes.list}>
                    <TextInput
                        size="sm"
                        leftSection={<IconSearch size={14}/>}
                        placeholder={t`Search templates`}
                        value={search}
                        onChange={event => setSearch(event.currentTarget.value)}
                        data-autofocus
                        aria-label={t`Search templates`}
                    />
                    <ScrollArea.Autosize className={classes.choices} type="auto" scrollbars="y" classNames={{viewport: classes.viewport}}>
                        {groups.map(group => {
                            const items = visible.filter(choice => choice.group === group.key);
                            if (items.length === 0) {
                                return null;
                            }
                            return (
                                <div key={group.key} className={classes.group}>
                                    {group.label && <div className={classes.groupLabel}>{group.label}</div>}
                                    {items.map(choice => (
                                        <UnstyledButton
                                            key={choice.id}
                                            className={classes.item}
                                            data-selected={choice.id === selected?.id || undefined}
                                            onClick={() => setSelectedId(choice.id)}
                                            onDoubleClick={() => start(choice)}
                                            data-testid={`campaign-picker-${choice.id.replace(':', '-')}`}
                                        >
                                            <group.icon size={16} stroke={1.6}/>
                                            <span className={classes.itemText}>
                                                <strong>{choice.label}</strong>
                                                {choice.hint && <span>{choice.hint}</span>}
                                            </span>
                                        </UnstyledButton>
                                    ))}
                                </div>
                            );
                        })}
                        {templatesQuery.isLoading && <Skeleton height={120} radius="md"/>}
                        {templatesQuery.isError && (
                            <p className={classes.empty}>{t`We couldn't load the starters and your templates. You can still start from a blank campaign, or refresh the page.`}</p>
                        )}
                        {visible.length === 0 && <p className={classes.empty}>{t`Nothing matches your search.`}</p>}
                    </ScrollArea.Autosize>
                </div>
                <div className={classes.preview}>
                    {selected?.body ? (
                        previewQuery.data ? (
                            <>
                                <div className={classes.previewSubject}>
                                    <strong>{previewQuery.data.subject || t`No subject yet`}</strong>
                                    {previewQuery.data.preheader && <span>{previewQuery.data.preheader}</span>}
                                </div>
                                <iframe className={classes.frame} title={t`Template preview`} sandbox="" srcDoc={previewQuery.data.html}/>
                            </>
                        ) : (
                            <div className={classes.previewEmpty}>{previewQuery.isError ? t`We couldn't load the preview. Please try again.` : t`Loading preview…`}</div>
                        )
                    ) : (
                        <div className={classes.previewEmpty}>{t`An empty message, ready for your words.`}</div>
                    )}
                </div>
            </div>
            <ModalFooter>
                <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                <Button onClick={() => start(selected)} disabled={!selected} data-testid="campaign-picker-start">
                    {t({message: 'Start', context: 'Button that starts writing a new campaign'})}
                </Button>
            </ModalFooter>
        </Modal>
    );
};
