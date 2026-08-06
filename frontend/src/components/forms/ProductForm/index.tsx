import {t, Trans} from "@lingui/macro";
import {UseFormReturnType} from "@mantine/form";
import {
    EventType,
    Product,
    ProductPriceType,
    ProductType,
    TaxAndFee,
    TaxAndFeeType
} from "../../../types.ts";
import {
    Alert,
    Button,
    ComboboxItem,
    MultiSelect,
    NumberInput,
    SegmentedControl,
    Select,
    Switch,
    TextInput
} from "@mantine/core";
import {
    IconAlignLeft,
    IconCalendar,
    IconEye,
    IconFlame,
    IconPlus,
    IconPuzzle,
    IconReceipt,
    IconShirt,
    IconShoppingCart,
    IconTicket,
    IconUsers,
    IconWorld,
} from "@tabler/icons-react";
import {Callout} from "../../common/Callout";
import {useDisclosure} from "@mantine/hooks";
import {NavLink, useParams} from "react-router";
import {useEffect, useState} from "react";
import {getCurrencySymbol} from "../../../utilites/currency.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetTaxesAndFees} from "../../../queries/useGetTaxesAndFees.ts";
import classes from './ProductForm.module.scss';
import {Fieldset} from "../../common/Fieldset";
import {Editor} from "../../common/Editor";
import {InputGroup} from "../../common/InputGroup";
import classNames from "classnames";
import {InputLabelWithHelp} from "../../common/InputLabelWithHelp";
import {CreateTaxOrFeeModal} from "../../modals/CreateTaxOrFeeModal";
import {hasQuantityValue, ProductPriceTierForm, SeriesQuantityWarning} from "./ProductPriceTierForm.tsx";
import {LedgerRow, LedgerRowId} from "./LedgerRow.tsx";
import {ProductSelector} from "../../common/ProductSelector";
import {
    accessSummary,
    addonsSummary,
    descriptionSummary,
    eventPageSummary,
    highlightSummary,
    orderLimitsSummary,
    saleWindowSummary,
    taxAndFeeLabel,
    taxesSummary,
    waitlistSummary,
} from "./ledgerSummaries.ts";

interface ProductFormProps {
    form: UseFormReturnType<Product>,
    product?: Product,
}

const LEDGER_ROW_ORDER: LedgerRowId[] = [
    'description',
    'sale-window',
    'event-page',
    'waitlist',
    'taxes',
    'order-limits',
    'addons',
    'highlight',
    'access',
];

const FIELD_TO_LEDGER_ROW: Array<[RegExp, LedgerRowId]> = [
    [/^description$/, 'description'],
    [/^(sale_start_date|sale_end_date|hide_before_sale_start_date|hide_after_sale_end_date)$/, 'sale-window'],
    [/^(show_quantity_remaining|hide_when_sold_out|start_collapsed)$/, 'event-page'],
    [/^waitlist_enabled$/, 'waitlist'],
    [/^tax_and_fee_ids/, 'taxes'],
    [/^(min_per_order|max_per_order)$/, 'order-limits'],
    [/^(addon_product_ids|is_addon_only)$/, 'addons'],
    [/^(is_highlighted|highlight_message)$/, 'highlight'],
    [/^(is_hidden|is_hidden_without_promo_code)$/, 'access'],
];

export const ProductForm = ({form, product}: ProductFormProps) => {
    const {eventId} = useParams();
    const [openRows, setOpenRows] = useState<Set<LedgerRowId>>(new Set());
    const [taxFeeModalOpen, {open: openTaxFeeModal, close: closeTaxFeeModal}] = useDisclosure(false);
    const isFreeProduct = form.values.type === 'FREE';
    const isDonationProduct = form.values.type === 'DONATION';
    const {data: event} = useGetEvent(eventId);
    const {data: taxesAndFees} = useGetTaxesAndFees();
    const isRecurring = event?.type === EventType.RECURRING;
    const isRecurringTicket = isRecurring && form.values.product_type === 'TICKET';
    const typeLocked = Number(product?.quantity_sold) > 0;

    const handleTaxOrFeeCreated = (taxOrFee: TaxAndFee) => {
        const currentIds = form.values.tax_and_fee_ids || [];
        form.setFieldValue('tax_and_fee_ids', [...currentIds, String(taxOrFee.id)]);
    };

    const taxAndFeeOptions = (type: TaxAndFeeType): ComboboxItem[] => {
        return taxesAndFees?.data
            ?.filter((item) => item.type === type)
            .map((item: TaxAndFee) => ({
                label: taxAndFeeLabel(item, event?.currency),
                value: String(item.id),
            })) || [];
    }

    useEffect(() => {
        if (form.values.type === ProductPriceType.Free && form.values.price !== 0.00) {
            form.setFieldValue('price', 0.00);
        }
    }, [form.values.type, form.values.price]);

    useEffect(() => {
        if (event?.product_categories && event.product_categories.length === 1) {
            const categoryId = String(event.product_categories[0].id);
            if (form.values.product_category_id !== categoryId) {
                form.setFieldValue('product_category_id', categoryId);
                form.resetDirty();
            }
        }
    }, [event?.product_categories]);

    useEffect(() => {
        const errorRows = new Set<LedgerRowId>();
        Object.keys(form.errors).forEach((field) => {
            const match = FIELD_TO_LEDGER_ROW.find(([pattern]) => pattern.test(field));
            if (match) {
                errorRows.add(match[1]);
            }
        });

        if (errorRows.size === 0) {
            return;
        }

        setOpenRows((previous) => new Set([...previous, ...errorRows]));

        const firstErrorRow = LEDGER_ROW_ORDER.find((rowId) => errorRows.has(rowId));
        if (firstErrorRow) {
            requestAnimationFrame(() => {
                document.getElementById(`product-ledger-row-${firstErrorRow}`)
                    ?.scrollIntoView({block: 'nearest', behavior: 'smooth'});
            });
        }
    }, [form.errors]);

    const toggleRow = (rowId: LedgerRowId) => {
        setOpenRows((previous) => {
            const next = new Set(previous);
            if (next.has(rowId)) {
                next.delete(rowId);
            } else {
                next.add(rowId);
            }
            return next;
        });
    };

    const removeTaxesAndFees = () => {
        form.setFieldValue('tax_and_fee_ids', []);
    };

    const showCategorySelect = (event?.product_categories?.length ?? 0) >= 2;

    const nameInput = (
        <TextInput
            {...form.getInputProps('title')}
            label={t`Name`}
            placeholder={form.values.product_type === 'TICKET' ? t`VIP Ticket` : t`T-shirt`}
            required
        />
    );

    return (
        <>
            {typeLocked && (
                <Callout variant="info">
                    {t`You cannot change the product type as there are attendees associated with this product.`}
                </Callout>
            )}

            <div
                role="radiogroup"
                aria-label={t`Product Type`}
                aria-required
                className={classes.typeCards}
            >
                {[
                    {
                        value: ProductType.Ticket,
                        icon: <IconTicket size={20}/>,
                        label: t`Ticket`,
                        description: t`Admits attendees to your event`,
                        testId: 'product-type-ticket',
                    },
                    {
                        value: ProductType.General,
                        icon: <IconShirt size={20}/>,
                        label: t`Product`,
                        description: t`T-shirts, mugs and more`,
                        testId: 'product-type-general',
                    },
                ].map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        role="radio"
                        aria-checked={form.values.product_type === option.value}
                        className={classNames(
                            classes.typeCard,
                            form.values.product_type === option.value && classes.selected,
                        )}
                        disabled={typeLocked}
                        data-testid={option.testId}
                        onClick={() => form.setFieldValue('product_type', option.value)}
                    >
                        <span className={classes.typeCardIcon}>{option.icon}</span>
                        <span className={classes.typeCardText}>
                            <span className={classes.typeCardLabel}>{option.label}</span>
                            <span className={classes.typeCardDescription}>{option.description}</span>
                        </span>
                    </button>
                ))}
            </div>

            {form.errors.product_type && (
                <Alert title={t`Product Type`} mb={20} color={'red'}>
                    {form.errors.product_type}
                </Alert>
            )}

            {showCategorySelect ? (
                <InputGroup>
                    {nameInput}
                    <Select
                        {...form.getInputProps('product_category_id')}
                        label={<InputLabelWithHelp
                            label={t`Product Category`}
                            helpText={t`Categories help you organize your products. This title will be displayed on the public event page.`}
                        />}
                        placeholder={t`Select category...`}
                        data={event?.product_categories?.map((category) => ({
                            value: String(category.id),
                            label: category.name,
                        }))}
                    />
                </InputGroup>
            ) : nameInput}

            <div className={classes.priceBlock}>
                <div className={classes.priceBlockHeader}>
                    <span className={classes.priceBlockLabel}>{t`Pricing`}</span>
                    <SegmentedControl
                        size="xs"
                        value={form.values.type}
                        onChange={(value) => form.setFieldValue('type', value as ProductPriceType)}
                        disabled={typeLocked}
                        data-testid="product-price-type"
                        data={[
                            {label: t`Paid`, value: ProductPriceType.Paid},
                            {label: t`Free`, value: ProductPriceType.Free},
                            {label: t`Donation`, value: ProductPriceType.Donation},
                            {label: t`Tiers`, value: ProductPriceType.Tiered},
                        ]}
                    />
                </div>

                {form.errors.type && (
                    <Alert title={t`Product Price Type`} mb={20} color={'red'}>
                        {form.errors.type}
                    </Alert>
                )}

                {form.values.type !== ProductPriceType.Tiered && (
                    <>
                        <InputGroup>
                            <NumberInput decimalScale={2}
                                         min={0}
                                         fixedDecimalScale
                                         disabled={isFreeProduct}
                                         leftSection={event?.currency ? getCurrencySymbol(event.currency) : ''}
                                         {...form.getInputProps('prices.0.price')}
                                         label={<InputLabelWithHelp
                                             label={isDonationProduct ? t`Minimum Price` : t`Price`}
                                             helpText={(
                                                 <Trans>
                                                     <p>
                                                         Please enter the price excluding taxes and fees.
                                                     </p>
                                                     <p>
                                                         Taxes and fees can be added below.
                                                     </p>
                                                 </Trans>
                                             )}
                                         />}
                                         placeholder="19.99"/>
                            <NumberInput min={0}
                                         placeholder={t`Unlimited`}
                                         {...form.getInputProps('prices.0.initial_quantity_available')}
                                         label={<InputLabelWithHelp
                                             label={isRecurringTicket ? t`Total Quantity Across All Dates` : t`Quantity Available`}
                                             helpText={isRecurringTicket ? (
                                                 <Trans>
                                                     <p>
                                                         This is the total quantity available across every date in your
                                                         schedule combined — not a per-date limit. To limit attendance for
                                                         each date, set a capacity on the <NavLink
                                                         to={`/manage/event/${eventId}/occurrences`}>Occurrence Schedule
                                                         page</NavLink>.
                                                     </p>
                                                 </Trans>
                                             ) : (
                                                 <Trans>
                                                     <p>
                                                         The number of products available for this product
                                                     </p>
                                                     <p>
                                                         This value can be overridden if there are <a target={'__blank'}
                                                                                                      href={'capacity-assignments'}>Capacity
                                                         Limits</a> associated with this product.
                                                     </p>
                                                 </Trans>
                                             )}
                                         />}
                            />
                        </InputGroup>
                        {!product && isRecurringTicket && hasQuantityValue(form.values.prices?.[0]?.initial_quantity_available) && (
                            <SeriesQuantityWarning eventId={eventId}/>
                        )}
                    </>
                )}

                {form.values.type === ProductPriceType.Tiered && (
                    <>
                        <Callout variant="info" title={t`What are Tiered Products?`}>
                            <Trans>
                                Tiered products allow you to offer multiple price options for the same product.
                                This is perfect for early bird products, or offering different price
                                options for different groups of people.
                            </Trans>
                        </Callout>
                        <Fieldset legend={t`Price Tiers`} mt={20} mb={20}>
                            {isRecurring && (
                                <Callout variant="info" style={{marginBottom: 10}}>
                                    <Trans>These prices apply across all dates in your schedule, and tier quantities limit
                                        total sales across all dates combined. Sale dates on tiers apply globally. You can
                                        override prices for individual dates on the <NavLink
                                            to={`/manage/event/${eventId}/occurrences`}>Occurrence Schedule
                                            page</NavLink>.</Trans>
                                </Callout>
                            )}
                            <div className={classes.priceTiers}>
                                <ProductPriceTierForm product={product} form={form} event={event}/>
                                <Button
                                    className={classes.addTierButton}
                                    size={'xs'}
                                    variant={'light'}
                                    data-testid="product-add-tier-button"
                                    leftSection={<IconPlus size={14}/>}
                                    onClick={() =>
                                        form.insertListItem('prices', {
                                            price: 0,
                                            label: undefined,
                                            sale_end_date: undefined,
                                            sale_start_date: undefined
                                        })
                                    }
                                >
                                    {t`Add tier`}
                                </Button>
                            </div>
                        </Fieldset>
                    </>
                )}
            </div>

            <div className={classes.ledger}>
                <div className={classes.ledgerHeading}>{t`Everything else — tap to edit`}</div>

                <LedgerRow
                    id="description"
                    icon={<IconAlignLeft size={16}/>}
                    label={t`Description`}
                    summary={descriptionSummary(form.values)}
                    opened={openRows.has('description')}
                    onToggle={toggleRow}
                >
                    <Editor
                        label={t`Description`}
                        value={form.values.description || ''}
                        onChange={(value) => form.setFieldValue('description', value)}
                        error={form.errors.description as string}
                    />
                </LedgerRow>

                <LedgerRow
                    id="sale-window"
                    icon={<IconCalendar size={16}/>}
                    label={t`Sale window`}
                    summary={saleWindowSummary(form.values)}
                    opened={openRows.has('sale-window')}
                    onToggle={toggleRow}
                >
                    {isRecurring && (
                        <Callout variant="info" style={{marginBottom: 10}}>
                            <Trans>Sale period dates apply across all dates in your schedule. To control pricing and
                                availability for individual dates, use the overrides on the <NavLink
                                    to={`/manage/event/${eventId}/occurrences`}>Occurrence Schedule page</NavLink>.</Trans>
                        </Callout>
                    )}
                    <InputGroup>
                        <TextInput type={'datetime-local'} {...form.getInputProps('sale_start_date')}
                                   label={t`Sale Start Date`}/>
                        <TextInput type={'datetime-local'} {...form.getInputProps('sale_end_date')}
                                   label={t`Sale End Date`}/>
                    </InputGroup>
                    <Switch {...form.getInputProps('hide_before_sale_start_date', {type: 'checkbox'})}
                            label={t`Hide product before sale start date`}
                            description={isRecurring ? t`Based on the global sale period above, not per date` : undefined}/>
                    <Switch {...form.getInputProps('hide_after_sale_end_date', {type: 'checkbox'})}
                            label={t`Hide product after sale end date`}
                            description={isRecurring ? t`Based on the global sale period above, not per date` : undefined}/>
                </LedgerRow>

                <LedgerRow
                    id="event-page"
                    icon={<IconEye size={16}/>}
                    label={t`On the event page`}
                    summary={eventPageSummary(form.values)}
                    opened={openRows.has('event-page')}
                    onToggle={toggleRow}
                >
                    <div className={classes.switchStack}>
                        <Switch {...form.getInputProps('show_quantity_remaining', {type: 'checkbox'})}
                                label={t`Show available product quantity`}/>
                        <Switch {...form.getInputProps('hide_when_sold_out', {type: 'checkbox'})}
                                label={t`Hide product when sold out`}/>
                        <Switch {...form.getInputProps('start_collapsed', {type: 'checkbox'})}
                                label={t`Collapse this product when the event page is initially loaded`}/>
                    </div>
                </LedgerRow>

                <LedgerRow
                    id="waitlist"
                    icon={<IconUsers size={16}/>}
                    label={t`Waitlist`}
                    summary={waitlistSummary(form.values)}
                    opened={openRows.has('waitlist')}
                    onToggle={toggleRow}
                >
                    <Switch
                        description={isRecurring
                            ? t`Allow customers to join a waitlist when this product is sold out. Customers join the waitlist for a specific date.`
                            : t`Allow customers to join a waitlist when this product is sold out`}
                        {...form.getInputProps(`waitlist_enabled`, {type: 'checkbox'})}
                        label={t`Enable Waitlist`}
                    />
                </LedgerRow>

                <LedgerRow
                    id="taxes"
                    icon={<IconReceipt size={16}/>}
                    label={t`Taxes & fees`}
                    summary={taxesSummary(form.values, taxesAndFees?.data, event?.currency)}
                    opened={openRows.has('taxes')}
                    onToggle={toggleRow}
                >
                    <MultiSelect
                        {...form.getInputProps('tax_and_fee_ids')}
                        label={t`Taxes and Fees`}
                        placeholder={t`Select...`}
                        data={[{
                            group: t`Taxes`,
                            items: taxAndFeeOptions(TaxAndFeeType.Tax),
                        }, {
                            group: t`Fees`,
                            items: taxAndFeeOptions(TaxAndFeeType.Fee),
                        }]}
                    />
                    <Button
                        variant="subtle"
                        size="compact-sm"
                        leftSection={<IconPlus size={14}/>}
                        onClick={openTaxFeeModal}
                        className={classes.addTaxFeeButton}
                    >
                        {t`Create Tax or Fee`}
                    </Button>

                    {(form.values.type === ProductPriceType.Free && !!form.values.tax_and_fee_ids?.length) && (
                        <Callout variant="info" style={{marginTop: 15}}>
                            <p>
                                {t`You have taxes and fees added to a Free Product. Would you like to remove them?`}
                            </p>
                            <Button onClick={removeTaxesAndFees} size={'xs'}>{t`Yes, remove them`}</Button>
                        </Callout>
                    )}
                </LedgerRow>

                <LedgerRow
                    id="order-limits"
                    icon={<IconShoppingCart size={16}/>}
                    label={t`Order limits`}
                    summary={orderLimitsSummary(form.values)}
                    opened={openRows.has('order-limits')}
                    onToggle={toggleRow}
                >
                    <InputGroup>
                        <NumberInput {...form.getInputProps('min_per_order')} label={t`Minimum Per Order`}
                                     placeholder="1"/>
                        <NumberInput {...form.getInputProps('max_per_order')} label={t`Maximum Per Order`}
                                     placeholder="10"/>
                    </InputGroup>
                </LedgerRow>

                <LedgerRow
                    id="addons"
                    icon={<IconPuzzle size={16}/>}
                    label={t`Add-ons`}
                    summary={addonsSummary(form.values)}
                    opened={openRows.has('addons')}
                    onToggle={toggleRow}
                >
                    <Switch
                        {...form.getInputProps('is_addon_only', {type: 'checkbox'})}
                        onChange={(changeEvent) => {
                            form.setFieldValue('is_addon_only', changeEvent.currentTarget.checked);
                            if (changeEvent.currentTarget.checked) {
                                form.setFieldValue('addon_product_ids', []);
                            }
                        }}
                        label={t`Only available as an add-on`}
                        description={t`This product won't appear on the event page on its own — buyers only see it as an add-on to the products it's attached to.`}
                    />
                    {!form.values.is_addon_only && (
                        <div style={{marginTop: 15}}>
                            <ProductSelector
                                label={t`Add-on products`}
                                placeholder={t`Select products...`}
                                icon={<IconPuzzle size={16}/>}
                                productCategories={event?.product_categories || []}
                                form={form}
                                productFieldName="addon_product_ids"
                                excludedProductIds={product?.id ? [product.id] : []}
                                noProductsMessage={t`Create more tickets or products to offer them as add-ons`}
                            />
                            <p className={classes.fieldHint}>
                                {t`Buyers can add these to their order when they select this product at checkout.`}
                            </p>
                        </div>
                    )}
                </LedgerRow>

                <LedgerRow
                    id="highlight"
                    icon={<IconFlame size={16}/>}
                    label={t`Highlight`}
                    summary={highlightSummary(form.values)}
                    opened={openRows.has('highlight')}
                    onToggle={toggleRow}
                >
                    <Switch
                        {...form.getInputProps('is_highlighted', {type: 'checkbox'})}
                        label={t`Highlight this product`}
                        description={t`Highlighted products will have a different background color to make them stand out on the event page.`}
                    />
                    {form.values.is_highlighted && (
                        <TextInput
                            mt={15}
                            {...form.getInputProps('highlight_message')}
                            label={t`Highlight Message`}
                            description={t`An optional message to display on the highlighted product, e.g. "Selling fast 🔥" or "Best value"`}
                            placeholder={t`Selling fast 🔥`}
                            maxLength={255}
                        />
                    )}
                </LedgerRow>

                <LedgerRow
                    id="access"
                    icon={<IconWorld size={16}/>}
                    label={t`Access`}
                    summary={accessSummary(form.values)}
                    opened={openRows.has('access')}
                    onToggle={toggleRow}
                >
                    <div className={classes.switchStack}>
                        <Switch
                            description={<>{t`You can create a promo code which targets this product on the`}
                                <NavLink
                                    target={'_blank'}
                                    to={'../promo-codes'}>{t`Promo Code page`}</NavLink></>}
                            {...form.getInputProps('is_hidden_without_promo_code', {type: 'checkbox'})}
                            label={t`Hide product unless user has applicable promo code`}
                        />
                        <Switch
                            description={t`This overrides all visibility settings and will hide the product from all customers.`}
                            {...form.getInputProps(`is_hidden`, {type: 'checkbox'})}
                            label={t`Hide this product from customers`}
                        />
                    </div>
                </LedgerRow>
            </div>

            {taxFeeModalOpen && (
                <CreateTaxOrFeeModal
                    onClose={closeTaxFeeModal}
                    onCreated={handleTaxOrFeeCreated}
                />
            )}
        </>
    );
};
