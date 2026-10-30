import {ClipboardEvent, KeyboardEvent, useEffect, useId, useRef, useState} from "react";
import {ActionIcon, Button, Text, TextInput} from "@mantine/core";
import {UseFormReturnType} from "@mantine/form";
import {t} from "@lingui/macro";
import {IconGripVertical, IconPlus, IconX} from "@tabler/icons-react";
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from "@dnd-kit/core";
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import {CSS} from "@dnd-kit/utilities";
import {QuestionFormValues} from "./questionFormValues.ts";
import classes from "./QuestionForm.module.scss";

interface OptionRowProps {
    id: string;
    index: number;
    value: string;
    error?: boolean;
    canRemove: boolean;
    inputRef: (element: HTMLInputElement | null) => void;
    onChange: (value: string) => void;
    onKeyDown: (event: KeyboardEvent<HTMLInputElement>) => void;
    onPaste: (event: ClipboardEvent<HTMLInputElement>) => void;
    onRemove: () => void;
}

const OptionRow = ({
                       id,
                       index,
                       value,
                       error,
                       canRemove,
                       inputRef,
                       onChange,
                       onKeyDown,
                       onPaste,
                       onRemove,
                   }: OptionRowProps) => {
    const {attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging} = useSortable({id});
    const position = index + 1;

    return (
        <div
            ref={setNodeRef}
            style={{transform: CSS.Transform.toString(transform), transition}}
            className={classes.optionRow}
            data-dragging={isDragging || undefined}
        >
            <button
                type="button"
                ref={setActivatorNodeRef}
                className={classes.optionHandle}
                aria-label={t`Reorder option ${position}`}
                {...attributes}
                {...listeners}
            >
                <IconGripVertical size={16} stroke={1.5}/>
            </button>
            <TextInput
                ref={inputRef}
                className={classes.optionInput}
                value={value}
                error={error}
                placeholder={t`Option ${position}`}
                aria-label={t`Option ${position}`}
                onChange={(event) => onChange(event.currentTarget.value)}
                onKeyDown={onKeyDown}
                onPaste={onPaste}
                data-testid="question-option-input"
            />
            <ActionIcon
                variant="subtle"
                color="gray"
                onClick={onRemove}
                disabled={!canRemove}
                aria-label={t`Remove option ${position}`}
            >
                <IconX size={16}/>
            </ActionIcon>
        </div>
    );
};

export const OptionsEditor = ({form}: { form: UseFormReturnType<QuestionFormValues> }) => {
    const options = form.values.options;
    const keyPrefix = useId();
    const keyCounter = useRef(0);
    const createKey = () => `${keyPrefix}-${keyCounter.current++}`;
    const originalLabels = useRef(new Map<string, string>());
    const createOriginalKeys = (labels: string[]) => {
        const renamedFrom = new Map((form.values.option_renames ?? []).map(({from, to}) => [to, from]));

        return labels.map((label) => {
            const key = createKey();
            if (label.trim() !== '') {
                originalLabels.current.set(key, renamedFrom.get(label) ?? label);
            }
            return key;
        });
    };
    const [keys, setKeys] = useState<string[]>(() => createOriginalKeys(options));
    const ineligibleOptions = form.values.disqualifying_options ?? [];
    const [keyToFocus, setKeyToFocus] = useState<string | null>(null);
    const inputs = useRef(new Map<string, HTMLInputElement>());
    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(KeyboardSensor, {coordinateGetter: sortableKeyboardCoordinates}),
    );
    const dndId = useId();

    useEffect(() => {
        if (keys.length !== options.length) {
            originalLabels.current.clear();
            setKeys(createOriginalKeys(options));
        }
    }, [options.length]);

    useEffect(() => {
        if (keyToFocus) {
            inputs.current.get(keyToFocus)?.focus();
            setKeyToFocus(null);
        }
    }, [keyToFocus, keys]);

    const setOptions = (nextOptions: string[], nextKeys: string[]) => {
        setKeys(nextKeys);
        form.setFieldValue('options', nextOptions);
        form.setFieldValue('option_renames', nextKeys
            .map((key, index) => ({from: originalLabels.current.get(key), to: nextOptions[index]}))
            .filter((rename): rename is { from: string; to: string } => rename.from !== undefined && rename.from !== rename.to));
        form.setFieldValue('disqualifying_options', ineligibleOptions.filter((option) => nextOptions.includes(option)));
    };

    const updateOption = (index: number, value: string) => {
        const previous = options[index];
        const nextOptions = options.map((option, i) => i === index ? value : option);
        setOptions(nextOptions, keys);
        if (ineligibleOptions.includes(previous)) {
            form.setFieldValue('disqualifying_options', [
                ...ineligibleOptions.filter((option) => option !== previous && nextOptions.includes(option)),
                value,
            ]);
        }
    };

    const insertAfter = (index: number, values: string[]) => {
        const newKeys = values.map(() => createKey());
        setOptions(
            [...options.slice(0, index + 1), ...values, ...options.slice(index + 1)],
            [...keys.slice(0, index + 1), ...newKeys, ...keys.slice(index + 1)],
        );
        setKeyToFocus(newKeys[newKeys.length - 1]);
    };

    const remove = (index: number) => {
        setOptions(options.filter((_, i) => i !== index), keys.filter((_, i) => i !== index));
        const neighbour = keys[index - 1] ?? keys[index + 1];
        if (neighbour) {
            setKeyToFocus(neighbour);
        }
    };

    const handleKeyDown = (index: number) => (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            insertAfter(index, ['']);
            return;
        }

        if (event.key === 'Backspace' && options[index] === '' && options.length > 1) {
            event.preventDefault();
            remove(index);
        }
    };

    const handlePaste = (index: number) => (event: ClipboardEvent<HTMLInputElement>) => {
        const lines = event.clipboardData.getData('text')
            .split(/\r?\n/)
            .map((line) => line.trim())
            .filter((line) => line !== '');

        if (lines.length < 2) {
            return;
        }

        event.preventDefault();

        if (options[index].trim() === '') {
            const [first, ...rest] = lines;
            const withFirst = options.map((option, i) => i === index ? first : option);
            const newKeys = rest.map(() => createKey());
            setOptions(
                [...withFirst.slice(0, index + 1), ...rest, ...withFirst.slice(index + 1)],
                [...keys.slice(0, index + 1), ...newKeys, ...keys.slice(index + 1)],
            );
            setKeyToFocus(newKeys[newKeys.length - 1] ?? keys[index]);
            return;
        }

        insertAfter(index, lines);
    };

    const handleDragEnd = ({active, over}: DragEndEvent) => {
        if (!over || active.id === over.id) {
            return;
        }

        const from = keys.indexOf(String(active.id));
        const to = keys.indexOf(String(over.id));
        setOptions(arrayMove(options, from, to), arrayMove(keys, from, to));
    };

    const addOption = () => insertAfter(options.length - 1, ['']);
    const optionsError = form.errors.options;

    return (
        <div className={classes.options}>
            {options.length > 0 && keys.length === options.length && (
                <DndContext id={dndId} sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
                    <SortableContext items={keys} strategy={verticalListSortingStrategy}>
                        <div className={classes.optionList}>
                            {options.map((option, index) => (
                                <OptionRow
                                    key={keys[index]}
                                    id={keys[index]}
                                    index={index}
                                    value={option}
                                    error={!!optionsError && option.trim() === ''}
                                    canRemove={options.length > 1}
                                    inputRef={(element) => {
                                        if (element) {
                                            inputs.current.set(keys[index], element);
                                        } else {
                                            inputs.current.delete(keys[index]);
                                        }
                                    }}
                                    onChange={(value) => updateOption(index, value)}
                                    onKeyDown={handleKeyDown(index)}
                                    onPaste={handlePaste(index)}
                                    onRemove={() => remove(index)}
                                />
                            ))}
                        </div>
                    </SortableContext>
                </DndContext>
            )}

            {optionsError && <Text size="xs" c="red" mt={6}>{optionsError}</Text>}

            <div className={classes.optionsFooter}>
                <Button
                    variant="subtle"
                    size="xs"
                    leftSection={<IconPlus size={14}/>}
                    onClick={addOption}
                >
                    {t`Add option`}
                </Button>
                <Text size="xs" c="dimmed">
                    {t`Press Enter for the next option, or paste a list.`}
                </Text>
            </div>
        </div>
    );
};
