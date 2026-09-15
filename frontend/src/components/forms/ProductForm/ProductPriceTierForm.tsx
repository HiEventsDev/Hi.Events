import {t, Trans} from "@lingui/macro";
import {UseFormReturnType} from "@mantine/form";
import {Event, EventType, Product, ProductPrice, ProductQuantityAppliesTo, ProductType} from "../../../types.ts";
import {ActionIcon, Menu, NumberInput, Switch, TextInput, UnstyledButton} from "@mantine/core";
import {IconCheck, IconChevronDown, IconGripVertical, IconTrash, IconTrashOff} from "@tabler/icons-react";
import {getCurrencySymbol} from "../../../utilites/currency.ts";
import {Card} from "../../common/Card";
import classes from './ProductForm.module.scss';
import {InputGroup} from "../../common/InputGroup";
import {showError} from "../../../utilites/notifications.tsx";
import classNames from "classnames";
import {InputLabelWithHelp} from "../../common/InputLabelWithHelp";
import {closestCenter, DndContext, DragEndEvent, PointerSensor, TouchSensor, useSensor, useSensors} from "@dnd-kit/core";
import {SortableContext, useSortable, verticalListSortingStrategy} from "@dnd-kit/sortable";
import {CSS} from "@dnd-kit/utilities";
import {ReactNode} from "react";

interface ProductPriceTierFormProps {
    form: UseFormReturnType<Product>,
    product?: Product,
    event?: Event,
}

export const defaultQuantityAppliesTo = (productType?: ProductType): ProductQuantityAppliesTo =>
    productType === ProductType.General ? ProductQuantityAppliesTo.Event : ProductQuantityAppliesTo.Occurrence;

interface QuantityFieldProps {
    form: UseFormReturnType<Product>;
    index: number;
    isRecurring: boolean;
    testId?: string;
}

export const QuantityField = ({form, index, isRecurring, testId}: QuantityFieldProps) => {
    const appliesTo = form.values.prices?.[index]?.quantity_applies_to
        ?? defaultQuantityAppliesTo(form.values.product_type);
    const perDate = isRecurring && appliesTo === ProductQuantityAppliesTo.Occurrence;

    const helpText = (() => {
        if (!isRecurring) {
            return (
                <Trans>
                    <p>The number of products available for this product</p>
                    <p>
                        This value can be overridden if there are <a target={'__blank'}
                                                                     href={'capacity-assignments'}>Capacity
                        Limits</a> associated with this product.
                    </p>
                </Trans>
            );
        }
        return perDate
            ? t`Applies to each date in your schedule. Dates with a capacity are also limited by it. You can override it for a single date on the Occurrence Schedule page.`
            : t`One pool shared by every date.`;
    })();

    const scopeOptions = [
        {
            value: ProductQuantityAppliesTo.Occurrence,
            label: t`Per date`,
            description: t`Applies to each date in your schedule`,
        },
        {
            value: ProductQuantityAppliesTo.Event,
            label: t`All dates`,
            description: t`One pool shared by every date.`,
        },
    ];

    const scopeMenu = isRecurring ? (
        <Menu position="bottom-end" width={260} shadow="md">
            <Menu.Target>
                <UnstyledButton className={classes.quantityScope} data-testid={testId}>
                    {perDate ? t`per date` : t`all dates`}
                    <IconChevronDown size={12}/>
                </UnstyledButton>
            </Menu.Target>
            <Menu.Dropdown>
                {scopeOptions.map((option) => (
                    <Menu.Item
                        key={option.value}
                        data-testid={`${testId}-option-${option.value}`}
                        rightSection={appliesTo === option.value ? <IconCheck size={14}/> : undefined}
                        onClick={() => form.setFieldValue(`prices.${index}.quantity_applies_to`, option.value)}
                    >
                        <div className={classes.quantityScopeOption}>
                            <span className={classes.quantityScopeOptionLabel}>{option.label}</span>
                            <span className={classes.quantityScopeOptionDescription}>{option.description}</span>
                        </div>
                    </Menu.Item>
                ))}
            </Menu.Dropdown>
        </Menu>
    ) : undefined;

    return (
        <NumberInput
            min={0}
            placeholder={t`Unlimited`}
            {...form.getInputProps(`prices.${index}.initial_quantity_available`)}
            label={<InputLabelWithHelp label={t`Quantity Available`} helpText={helpText}/>}
            rightSection={scopeMenu}
            rightSectionWidth={isRecurring ? 96 : undefined}
            rightSectionPointerEvents="all"
        />
    );
};

const SortableTierCard = ({index, sortable, children}: { index: number; sortable: boolean; children: ReactNode }) => {
    const {attributes, listeners, setNodeRef, transform, transition, isDragging} = useSortable({
        id: index,
        disabled: !sortable,
    });

    return (
        <div ref={setNodeRef} style={{transform: CSS.Transform.toString(transform), transition}}>
            <Card className={classNames([classes.priceTierCard, isDragging && classes.dragging])}>
                {sortable && (
                    <div {...attributes} {...listeners} className={classes.tierDragHandle}
                         data-testid={`product-tier-${index}-drag-handle`}>
                        <IconGripVertical size={18} stroke={1.5}/>
                    </div>
                )}
                {children}
            </Card>
        </div>
    );
};

export const ProductPriceTierForm = ({form, product, event}: ProductPriceTierFormProps) => {
    const isRecurring = event?.type === EventType.RECURRING;
    const prices: ProductPrice[] = form.values.prices ?? [];
    const sortable = prices.length > 1;
    const sensors = useSensors(useSensor(PointerSensor), useSensor(TouchSensor));

    const handleDragEnd = ({active, over}: DragEndEvent) => {
        if (over && active.id !== over.id) {
            form.reorderListItem('prices', {from: Number(active.id), to: Number(over.id)});
        }
    };

    return (
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
            <SortableContext items={prices.map((_, index) => index)} strategy={verticalListSortingStrategy}>
                {prices.map((price, index) => {
                    const existingPrice = product?.prices?.find((p) => Number(p.id) === Number(price.id));
                    const deleteDisabled = prices.length === 1 || (existingPrice && Number(existingPrice?.quantity_sold) > 0);
                    const cannotDeleteTitle = (() => {
                        if (existingPrice && Number(existingPrice?.quantity_sold) > 0) {
                            return t`You cannot delete this price tier because there are already products sold for this tier. You can hide it instead.`
                        }
                        if (prices.length === 1) {
                            return t`You must have at least one price tier`
                        }
                        return '';
                    })();

                    return (
                        <SortableTierCard key={`price-${index}`} index={index} sortable={sortable}>
                            <h3>{price.label || <Trans>Tier {index + 1}</Trans>}</h3>
                            <InputGroup>
                                <NumberInput decimalScale={2}
                                             min={0}
                                             fixedDecimalScale
                                             leftSection={event?.currency ? getCurrencySymbol(event.currency) : ''}
                                             {...form.getInputProps(`prices.${index}.price`)}
                                             label={t`Price`}
                                             placeholder="19.99"/>
                                <TextInput
                                    {...form.getInputProps(`prices.${index}.label`)}
                                    label={t`Label`}
                                    placeholder={t`Early bird`}
                                    required
                                />
                            </InputGroup>
                            <QuantityField
                                form={form}
                                index={index}
                                isRecurring={isRecurring}
                                testId={`product-tier-${index}-quantity-applies-to`}
                            />
                            <InputGroup>
                                <TextInput
                                    type={'datetime-local'}
                                    {...form.getInputProps(`prices.${index}.sale_start_date`)}
                                    label={t`Sale Start Date`}
                                />
                                <TextInput
                                    type={'datetime-local'}
                                    {...form.getInputProps(`prices.${index}.sale_end_date`)}
                                    label={t`Sale End Date`}
                                />
                            </InputGroup>

                            <Switch
                                mt={10}
                                description={t`Hiding a product will prevent users from seeing it on the event page.`}
                                {...form.getInputProps(`prices.${index}.is_hidden`, {type: 'checkbox'})}
                                label={t`Hide this tier from users`}
                            />

                            <ActionIcon
                                variant={'light'}
                                className={classNames([classes.removeTier, deleteDisabled && classes.disabled])}
                                title={cannotDeleteTitle}
                                onClick={() => {
                                    if (deleteDisabled) {
                                        showError(cannotDeleteTitle);
                                        return;
                                    }
                                    form.removeListItem('prices', index)
                                }}
                            >
                                {!deleteDisabled && <IconTrash size="1rem"/>}
                                {deleteDisabled && <IconTrashOff size="1rem"/>}
                            </ActionIcon>
                        </SortableTierCard>
                    );
                })}
            </SortableContext>
        </DndContext>
    );
}
