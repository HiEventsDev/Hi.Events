import {t} from "@lingui/macro";
import {EventDuplicatePayload, GenericModalProps, IdParam} from "../../../types.ts";
import {Button, Switch, TextInput, Group, ActionIcon, Tooltip, Grid} from "@mantine/core";
import {Modal} from "../../common/Modal";
import {useForm} from "@mantine/form";
import {useDuplicateEvent} from "../../../mutations/useDuplicateEvent.ts";
import {Editor} from "../../common/Editor";
import {InputGroup} from "../../common/InputGroup";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useNavigate} from "react-router";
import {useEffect} from "react";
import {utcToTz} from "../../../utilites/dates.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {Card} from "../../common/Card";
import {IconCheckbox, IconSquare} from "@tabler/icons-react";

interface DuplicateEventModalProps extends GenericModalProps {
    eventId: IdParam;
}

export const DuplicateEventModal = ({onClose, eventId}: DuplicateEventModalProps) => {
    const duplicateOptions = [
        { key: 'duplicate_products', label: t`Products` },
        { key: 'duplicate_questions', label: t`Questions` },
        { key: 'duplicate_settings', label: t`Settings` },
        { key: 'duplicate_promo_codes', label: t`Promo Codes` },
        { key: 'duplicate_capacity_assignments', label: t`Capacity Assignments` },
        { key: 'duplicate_check_in_lists', label: t`Check-In Lists` },
        { key: 'duplicate_event_cover_image', label: t`Event Cover Image` },
        { key: 'duplicate_ticket_logo', label: t`Ticket Logo` },
        { key: 'duplicate_webhooks', label: t`Webhooks` },
        { key: 'duplicate_affiliates', label: t`Affiliates` },
    ];

    const form = useForm({
        initialValues: {
            title: '',
            start_date: '',
            end_date: '',
            description: '',
            duplicate_products: true,
            duplicate_questions: true,
            duplicate_settings: true,
            duplicate_promo_codes: true,
            duplicate_capacity_assignments: true,
            duplicate_check_in_lists: true,
            duplicate_event_cover_image: true,
            duplicate_ticket_logo: true,
            duplicate_webhooks: true,
            duplicate_affiliates: true,
        }
    });
    const mutation = useDuplicateEvent();
    const eventQuery = useGetEvent(eventId);
    const nav = useNavigate();
    const errorHandler = useFormErrorResponseHandler();

    const handleSelectAll = () => {
        duplicateOptions.forEach(option => {
            form.setFieldValue(option.key, true);
        });
    };

    const handleDeselectAll = () => {
        duplicateOptions.forEach(option => {
            form.setFieldValue(option.key, false);
        });
    };

    const allSelected = duplicateOptions.every(option => form.values[option.key]);
    const noneSelected = duplicateOptions.every(option => !form.values[option.key]);

    useEffect(() => {
        if (eventQuery?.data) {
            form.setValues({
                title: eventQuery.data.title,
                description: eventQuery.data.description,
                start_date: utcToTz(eventQuery.data.start_date, eventQuery.data.timezone),
                end_date: utcToTz(eventQuery.data.end_date, eventQuery.data.timezone),
            });
        }
    }, [eventQuery.isFetched]);

    const handleDuplicate = (eventId: IdParam, duplicateData: EventDuplicatePayload) => {
        mutation.mutate({eventId, duplicateData}, {
            onSuccess: ({data}) => {
                nav(`/manage/event/${data.id}`);
                showSuccess(t`Event duplicated successfully`);
            },
            onError: (error) => {
                errorHandler(form, error);
            }
        });
    }

    return (
        <Modal
            onClose={onClose}
            heading={t`Duplicate Event`}
            opened
            size={'lg'}
            withCloseButton
        >
            <form onSubmit={form.onSubmit((values) => handleDuplicate(eventId, values))}>
                <fieldset disabled={eventQuery.isLoading || mutation.isPending}>
                    <TextInput
                        {...form.getInputProps('title')}
                        label={t`Name`}
                        placeholder={t`Summer Music Festival ${new Date().getFullYear()}`}
                        required
                    />

                    <Editor
                        label={t`Description`}
                        value={form.values.description || ''}
                        onChange={(value) => form.setFieldValue('description', value)}
                        error={form.errors?.description as string}
                    />

                    <InputGroup>
                        <TextInput type={'datetime-local'}
                                   {...form.getInputProps('start_date')}
                                   label={t`Start Date`}
                                   required
                        />
                        <TextInput type={'datetime-local'}
                                   {...form.getInputProps('end_date')}
                                   label={t`End Date`}
                        />
                    </InputGroup>

                    <Group justify="space-between" align="center" mb="md">
                        <h3 style={{margin: 0}}>
                            {t`Duplicate Options`}
                        </h3>
                        <Group gap="xs">
                            <Tooltip label={t`Select All`}>
                                <ActionIcon
                                    variant="light"
                                    size="sm"
                                    onClick={handleSelectAll}
                                    disabled={allSelected}
                                    color="blue"
                                >
                                    <IconCheckbox size={16} />
                                </ActionIcon>
                            </Tooltip>
                            <Tooltip label={t`Deselect All`}>
                                <ActionIcon
                                    variant="light"
                                    size="sm"
                                    onClick={handleDeselectAll}
                                    disabled={noneSelected}
                                    color="gray"
                                >
                                    <IconSquare size={16} />
                                </ActionIcon>
                            </Tooltip>
                        </Group>
                    </Group>
                    <Card variant={'lightGray'}>
                        <Grid>
                            {duplicateOptions.map((option, index) => (
                                <Grid.Col key={option.key} span={{ base: 12, sm: 6 }}>
                                    <Switch
                                        {...form.getInputProps(option.key, {type: 'checkbox'})}
                                        label={option.label}
                                        mb={index === duplicateOptions.length - 1 || index === duplicateOptions.length - 2 ? 0 : "xs"}
                                    />
                                </Grid.Col>
                            ))}
                        </Grid>
                    </Card>
                </fieldset>
                <Button type="submit" fullWidth disabled={mutation.isPending}>
                    {mutation.isPending ? t`Working...` : t`Duplicate Event`}
                </Button>
            </form>
        </Modal>
    )
}
