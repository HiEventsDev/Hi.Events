import {useState} from "react";
import {t} from "@lingui/macro";
import {Button, Textarea, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {Modal, ModalFooter} from "../../../../components/common/Modal";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {IdParam} from "../../../../types.ts";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {useCreateCrmSegment} from "../../mutations/useCreateCrmSegment.ts";
import {useUpdateCrmSegment} from "../../mutations/useUpdateCrmSegment.ts";
import {CrmSegment, CrmSegmentDefinition} from "../../types.ts";
import {isDefinitionComplete, SegmentBuilder} from "../SegmentBuilder";
import {Notice} from "../Notice";

const apiValidationErrors = (error: unknown): Record<string, string> =>
    (error as { response?: { data?: { errors?: Record<string, string> } } })?.response?.data?.errors ?? {};

const emptyDefinition = (): CrmSegmentDefinition => ({match: 'all', rules: []});

export const SegmentModal = ({organizerId, segment, initialDefinition, onClose, onSaved}: {
    organizerId: IdParam,
    segment?: CrmSegment,
    initialDefinition?: CrmSegmentDefinition,
    onClose: () => void,
    onSaved?: (segment: CrmSegment) => void,
}) => {
    const createSegment = useCreateCrmSegment();
    const updateSegment = useUpdateCrmSegment();
    const errorHandler = useFormErrorResponseHandler();
    const [definition, setDefinition] = useState<CrmSegmentDefinition>(segment?.definition ?? initialDefinition ?? emptyDefinition());
    const [ruleErrors, setRuleErrors] = useState<Record<string, string>>({});
    const form = useForm({
        initialValues: {name: segment?.name ?? '', description: segment?.description ?? ''},
        validate: {name: value => value.trim() ? null : t`Give this segment a name`},
    });
    const isPending = createSegment.isPending || updateSegment.isPending;

    const handleError = (error: unknown) => {
        setRuleErrors(apiValidationErrors(error));
        errorHandler(form, error);
    };

    const handleSubmit = (values: typeof form.values) => {
        const payload = {
            name: values.name.trim(),
            description: values.description.trim() || null,
            definition,
        };
        const options = {
            onSuccess: (response: { data: CrmSegment }) => {
                showSuccess(segment ? t`Segment updated` : t`Segment saved`);
                onSaved?.(response.data);
                onClose();
            },
            onError: handleError,
        };

        if (segment) {
            updateSegment.mutate({organizerId, segmentId: segment.id, segment: payload}, options);
        } else {
            createSegment.mutate({organizerId, segment: payload}, options);
        }
    };

    return (
        <Modal opened onClose={onClose} heading={segment ? t`Edit segment` : t`New segment`} size="xl">
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <TextInput
                    {...form.getInputProps('name')}
                    label={t`Name`}
                    placeholder={t`Summer festival regulars`}
                    maxLength={100}
                    required
                    data-autofocus
                    data-testid="segment-name"
                />
                <Textarea
                    {...form.getInputProps('description')}
                    label={t`Description`}
                    placeholder={t`Optional. Remind your team who this is for.`}
                    maxLength={500}
                    autosize
                    minRows={1}
                    maxRows={3}
                />
                {segment?.has_restricted_rules ? (
                    <Notice tone="info" title={t`Some rules are hidden`}>
                        {t`This segment filters by spend, which your role can't see. You can rename it, but only a member who can view financials can change its rules.`}
                    </Notice>
                ) : (
                    <SegmentBuilder
                        organizerId={organizerId}
                        value={definition}
                        onChange={next => {
                            setDefinition(next);
                            setRuleErrors({});
                        }}
                        errors={ruleErrors}
                        startsChoosingRule
                    />
                )}
                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button
                        type="submit"
                        loading={isPending}
                        disabled={!segment?.has_restricted_rules && !isDefinitionComplete(definition)}
                        data-testid="segment-save"
                    >
                        {segment ? t`Save changes` : t`Save segment`}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
};
