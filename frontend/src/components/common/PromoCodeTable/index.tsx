import {t} from "@lingui/macro";
import {WriteAccess} from "../WriteAccess";
import {CreateButton} from "../CreateButton";
import {Event, PromoCode, PromoCodeDiscountAppliesTo, PromoCodeDiscountType} from "../../../types.ts";
import {prettyDate, relativeDate} from "../../../utilites/dates.ts";
import {ActionIcon, Menu, Tooltip, VisuallyHidden} from "@mantine/core";
import {IconCopy, IconDotsVertical, IconPlus, IconSend, IconTrash} from "@tabler/icons-react";
import {TanStackTable, TanStackTableColumn} from "../TanStackTable";
import {CellCopy, CellText} from "../TanStackTable/cells.tsx";
import {sortableHeader, TableSort} from "../TanStackTable/sorting.tsx";
import {formatCurrency} from "../../../utilites/currency.ts";
import {useClipboard, useDisclosure} from "@mantine/hooks";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {EditPromoCodeModal} from "../../modals/EditPromoCodeModal";
import {useMemo, useState} from "react";
import {NoResultsSplash} from "../NoResultsSplash";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {useDeletePromoCode} from "../../../mutations/useDeletePromoCode.ts";
import {eventHomepageUrl} from "../../../utilites/urlHelper.ts";
import {getProductsFromEvent} from "../../../utilites/helpers.ts";

interface PromoCodeTableProps {
    event: Event,
    promoCodes: PromoCode[],
    openCreateModal: () => void,
    isRefreshing?: boolean,
    sort?: TableSort,
}

export const PromoCodeTable = ({event, promoCodes, openCreateModal, isRefreshing, sort}: PromoCodeTableProps) => {
    const [promoCodeId, setPromoCodeId] = useState<number | undefined>();
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const deleteMutation = useDeletePromoCode();
    const clipboard = useClipboard({ timeout: 500 });
    const eventProducts = getProductsFromEvent(event);

    const handleEditModal = (promoCodeId: number | undefined) => {
        setPromoCodeId(promoCodeId);
        openEditModal();
    };

    const handleDeleteCode = (promoCodeId: number) => {
        confirmationDialog(
            t`Are you sure you want to delete this promo code?`,
            () => {
                deleteMutation.mutate({eventId: event.id, promoCodeId});
            }, {confirm: t`Delete`, cancel: t`Cancel`}
        );
    }

    const discountLabel = (code: PromoCode) => {
        if (!code.discount) {
            return t`None`;
        }
        if (code.discount_type === PromoCodeDiscountType.Fixed) {
            const scope = code.discount_applies_to === PromoCodeDiscountAppliesTo.Order ? t`per order` : t`per product`;
            return `${formatCurrency(code.discount, event.currency)} ${scope}`;
        }
        return `${code.discount}%`;
    };

    const columns = useMemo<TanStackTableColumn<PromoCode>[]>(() => [
        {
            id: 'code',
            enableHiding: false,
            ...sortableHeader(t`Code`, 'code', sort, 'asc'),
            meta: {label: t`Code`, sticky: 'left'},
            cell: ({row: {original: code}}) => (
                <CellCopy value={code.code.toUpperCase()}>
                    <CellText mono strong title={code.code.toUpperCase()}>{code.code.toUpperCase()}</CellText>
                </CellCopy>
            ),
        },
        {
            id: 'discount',
            header: t`Discount`,
            cell: ({row: {original: code}}) => code.discount
                ? <CellText strong>{discountLabel(code)}</CellText>
                : <CellText muted>{discountLabel(code)}</CellText>,
        },
        {
            id: 'usage',
            ...sortableHeader(t`Times used`, 'order_usage_count', sort),
            meta: {label: t`Times used`, align: 'right'},
            cell: ({row: {original: code}}) => (
                <span>
                    {code.order_usage_count} / {code.max_allowed_usages
                    ? code.max_allowed_usages
                    : <span title={t`Unlimited usages allowed`}>∞</span>}
                </span>
            ),
        },
        {
            id: 'products',
            header: t`Products`,
            cell: ({row: {original: code}}) => {
                const productIds = code.applicable_product_ids?.map(Number) ?? [];
                if (productIds.length === 0) {
                    return t`All Products`;
                }
                const titles = eventProducts
                    ?.filter(product => productIds.includes(Number(product.id)))
                    .map(product => product.title) ?? [];

                return (
                    <Tooltip label={titles.join('\n')} multiline withArrow style={{whiteSpace: 'pre-line'}}>
                        <span>{productIds.length} {t`Product(s)`}</span>
                    </Tooltip>
                );
            },
        },
        {
            id: 'expires',
            header: t`Expires`,
            cell: ({row: {original: code}}) => code.expiry_date ? (
                <Tooltip label={prettyDate(String(code.expiry_date), event.timezone)} withArrow>
                    <span>{relativeDate(String(code.expiry_date))}</span>
                </Tooltip>
            ) : <CellText muted>{t`Never`}</CellText>,
        },
        {
            id: 'actions',
            header: () => <VisuallyHidden>{t`Actions`}</VisuallyHidden>,
            enableHiding: false,
            meta: {sticky: 'right'},
            cell: ({row: {original: code}}) => (
                <Menu shadow="md" width={200} position="bottom-end">
                    <Menu.Target>
                        <ActionIcon variant="subtle" color="gray" aria-label={t`Actions`} data-testid="promo-code-actions-button">
                            <IconDotsVertical size={18}/>
                        </ActionIcon>
                    </Menu.Target>

                    <Menu.Dropdown>
                        <Menu.Label>{t`Manage`}</Menu.Label>
                        <WriteAccess>
                            <Menu.Item leftSection={<IconSend size={14}/>} onClick={() => handleEditModal(code.id)}>
                                {t`Edit Code`}
                            </Menu.Item>
                        </WriteAccess>
                        <Menu.Item
                            leftSection={<IconCopy size={14}/>}
                            onClick={() => {
                                clipboard.copy(eventHomepageUrl(event) + `?promo_code=${code.code}`);
                                showSuccess(t`URL copied to clipboard`);
                            }}
                        >
                            {t`Copy URL`}
                        </Menu.Item>
                        <WriteAccess>
                            <Menu.Divider/>
                            <Menu.Label>{t`Danger zone`}</Menu.Label>
                            <Menu.Item
                                color="red"
                                onClick={() => handleDeleteCode(code.id as number)}
                                leftSection={<IconTrash size={14}/>}
                            >
                                {t`Delete code`}
                            </Menu.Item>
                        </WriteAccess>
                    </Menu.Dropdown>
                </Menu>
            ),
        },
    ], [event, promoCodes, sort?.sortBy, sort?.sortDirection]);

    if (promoCodes.length === 0) {
        return <NoResultsSplash
            heading={t`No Promo Codes to show`}
            imageHref={'/blank-slate/promo-codes.svg'}
            subHeading={(
                <>
                    <p>
                        {t`Promo codes can be used to offer discounts, presale access, or provide special access to your event.`}
                    </p>
                    <CreateButton
                        size={'xs'}
                        leftSection={<IconPlus/>}
                        color={'green'}
                        onClick={() => openCreateModal()}>{t`Create a Promo Code`}
                    </CreateButton>
                </>
            )}
        />
    }

    return (
        <>
            <TanStackTable data={promoCodes} columns={columns} noCard isRefreshing={isRefreshing}/>

            {(promoCodeId && editModalOpen) &&
                <EditPromoCodeModal promoCodeId={promoCodeId} onClose={closeEditModal}/>}
        </>
    )
}
