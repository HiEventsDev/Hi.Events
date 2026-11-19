import {useState} from "react";
import {Link, useParams} from "react-router";
import {t} from "@lingui/macro";
import {ActionIcon, Button, Menu, Skeleton} from "@mantine/core";
import {IconDotsVertical, IconPencil, IconPlus, IconSend, IconTrash} from "@tabler/icons-react";
import {CreateButton} from "../../../../../components/common/CreateButton";
import {firstApiError} from "../../../../../utilites/apiErrors.ts";
import {confirmationDialog} from "../../../../../utilites/confirmationDialog.tsx";
import {relativeDate} from "../../../../../utilites/dates.ts";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";
import {useCrmAccess} from "../../../hooks/useCrmAccess.ts";
import {useCrmReady} from "../../../hooks/useCrmReady.ts";
import {useDeleteCrmCampaignTemplate} from "../../../mutations/useCrmCampaignTemplateMutations.ts";
import {useGetCrmCampaignTemplates} from "../../../queries/useGetCrmCampaignTemplates.ts";
import {CrmCampaignStarter, CrmCampaignTemplate} from "../../../types.ts";
import {AudienceShell} from "../../AudienceShell";
import {LoadError} from "../../LoadError";
import shared from "../../Audience.module.scss";
import {TemplateModal} from "./TemplateModal.tsx";
import classes from "./Templates.module.scss";

const AudienceTemplates = () => {
    const {organizerId} = useParams();
    const isReady = useCrmReady(organizerId);
    const {canChange, canRemove} = useCrmAccess();
    const templatesQuery = useGetCrmCampaignTemplates(organizerId, isReady);
    const deleteTemplate = useDeleteCrmCampaignTemplate();
    const [editing, setEditing] = useState<{template?: CrmCampaignTemplate, starter?: CrmCampaignStarter} | null>(null);
    const templates = templatesQuery.data?.data ?? [];
    const starters = templatesQuery.data?.starters ?? [];
    const campaignsPath = `/manage/organizer/${organizerId}/audience/campaigns`;

    const remove = (template: CrmCampaignTemplate) => {
        const name = template.name;

        confirmationDialog(
            t`Delete the template "${name}"?`,
            () => deleteTemplate.mutate({organizerId, templateId: template.id}, {
                onSuccess: () => showSuccess(t`Template deleted`),
                onError: error => showError(firstApiError(error, t`We couldn't delete this template. Please try again.`)),
            }),
            {confirm: t`Delete template`, destructive: true, description: t`Campaigns you already made from it aren't affected.`},
        );
    };

    return (
        <AudienceShell
            actions={canChange && (
                <CreateButton color="green" rightSection={<IconPlus/>} onClick={() => setEditing({})} data-testid="template-create-button">
                    {t`New template`}
                </CreateButton>
            )}
        >
            <section className={shared.panel}>
                <div className={shared.panelFlush}>
                    {templatesQuery.isLoading && <Skeleton height={72} m="md" radius="md"/>}

                    {templatesQuery.isError && (
                        <LoadError message={t`We couldn't load your templates.`} onRetry={() => templatesQuery.refetch()}/>
                    )}

                    {templatesQuery.isSuccess && templates.length === 0 && (
                        <div className={shared.firstRun}>
                            {canChange && <h2 className={shared.panelTitle}>{t`Create your first template`}</h2>}
                            <p>
                                {canChange
                                    ? t`No templates yet. Start from one of these, or open any campaign and choose Save as template.`
                                    : t`No templates yet. A template is saved from a campaign or created here, and anyone on your team can start a campaign from it.`}
                            </p>
                        </div>
                    )}

                    {templatesQuery.isSuccess && templates.length === 0 && canChange && starters.map(starter => (
                        <div key={starter.key} className={classes.row} data-starter>
                            <div className={classes.title}>
                                <h3>{starter.name}</h3>
                                <p>{starter.description}</p>
                            </div>
                            <div className={classes.actions}>
                                <Button
                                    variant="default"
                                    size="sm"
                                    onClick={() => setEditing({starter})}
                                    data-testid={`template-starter-${starter.key}`}
                                >
                                    {t`Start from this`}
                                </Button>
                            </div>
                        </div>
                    ))}

                    {templates.map(template => {
                        const updated = relativeDate(template.updated_at);
                        const lastUsed = template.last_used_at ? relativeDate(template.last_used_at) : null;

                        return (
                            <div key={template.id} className={classes.row} data-testid="template-row">
                                <div className={classes.title}>
                                    <h3>{template.name}</h3>
                                    <p>{template.subject || t`No subject yet`}</p>
                                </div>
                                <div className={classes.meta}>
                                    <span>{t`Edited ${updated}`}</span>
                                    <span>{lastUsed ? t`Last used ${lastUsed}` : t`Not used yet`}</span>
                                </div>
                                <div className={classes.actions}>
                                    {canChange && (
                                        <Button
                                            component={Link}
                                            to={`${campaignsPath}/new?template=${template.id}`}
                                            variant="default"
                                            size="sm"
                                            leftSection={<IconSend size={14}/>}
                                            data-testid="template-use"
                                        >
                                            {t`Use`}
                                        </Button>
                                    )}
                                    {(canChange || canRemove) && (
                                        <Menu position="bottom-end" shadow="md" withinPortal>
                                            <Menu.Target>
                                                <ActionIcon variant="subtle" color="gray" size="lg" aria-label={t`Template actions`}>
                                                    <IconDotsVertical size={16}/>
                                                </ActionIcon>
                                            </Menu.Target>
                                            <Menu.Dropdown>
                                                {canChange && (
                                                    <Menu.Item leftSection={<IconPencil size={14}/>} onClick={() => setEditing({template})}>
                                                        {t`Edit`}
                                                    </Menu.Item>
                                                )}
                                                {canRemove && (
                                                    <Menu.Item color="red" leftSection={<IconTrash size={14}/>} onClick={() => remove(template)}>
                                                        {t`Delete`}
                                                    </Menu.Item>
                                                )}
                                            </Menu.Dropdown>
                                        </Menu>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </section>

            {editing && (
                <TemplateModal
                    organizerId={organizerId}
                    template={editing.template}
                    starter={editing.starter}
                    onClose={() => setEditing(null)}
                />
            )}
        </AudienceShell>
    );
};

export default AudienceTemplates;
