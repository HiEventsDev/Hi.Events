import {useState} from "react";
import {ActionIcon, Button, Center, Loader, Menu, Tooltip} from "@mantine/core";
import {useDisclosure} from "@mantine/hooks";
import {t} from "@lingui/macro";
import {IconDots, IconPencil, IconPlus, IconTrash} from "@tabler/icons-react";
import {useGetTaxesAndFees} from "../../../../queries/useGetTaxesAndFees.ts";
import {useGetAccount} from "../../../../queries/useGetAccount.ts";
import {useDeleteTaxOrFee} from "../../../../mutations/useDeleteTaxOrFee.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {TaxAndFee, TaxAndFeeCalculationType, TaxAndFeeType} from "../../../../types.ts";
import {CreateTaxOrFeeModal} from "../../../modals/CreateTaxOrFeeModal";
import {EditTaxOrFeeModal} from "../../../modals/EditTaxOrFeeModal";
import {SettingsPage, SettingsSection} from "../../../common/SettingsPage";
import {useAccess} from "../../../../hooks/useAccess.ts";
import classes from "./TaxesAndFees.module.scss";

const TaxesAndFees = () => {
    const {data: taxesAndFees, isFetched, isError, isFetching, refetch} = useGetTaxesAndFees();
    const {data: account} = useGetAccount();
    const deleteMutation = useDeleteTaxOrFee();
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const [editing, setEditing] = useState<TaxAndFee | null>(null);
    const {isReadOnly} = useAccess();

    const items = taxesAndFees?.data ?? [];
    const taxes = items.filter(item => item.type === TaxAndFeeType.Tax);
    const fees = items.filter(item => item.type === TaxAndFeeType.Fee);

    const handleDelete = (item: TaxAndFee) => {
        confirmationDialog(t`Are you sure you want to delete this tax or fee? It will no longer be applied to new orders.`, () => {
            deleteMutation.mutate({taxAndFeeId: item.id, accountId: item.account_id}, {
                onSuccess: () => showSuccess(item.type === TaxAndFeeType.Tax ? t`Tax deleted` : t`Fee deleted`),
                onError: () => showError(t`Something went wrong. Please try again.`),
            });
        });
    };

    const formatRate = (item: TaxAndFee) => item.calculation_type === TaxAndFeeCalculationType.Percentage
        ? `${Number(item.rate)}%`
        : formatCurrency(Number(item.rate), account?.currency_code);

    const renderItem = (item: TaxAndFee) => (
        <div key={item.id} className={classes.item} data-testid="tax-or-fee-row">
            <div className={classes.itemText}>
                <div className={classes.itemName}>
                    <span className={classes.itemNameText}>{item.name}</span>
                    {item.is_default && (
                        <Tooltip label={t`Added to new products automatically`} withArrow>
                            <span className={classes.tag}>{t`Default`}</span>
                        </Tooltip>
                    )}
                    {!item.is_active && <span className={classes.tag}>{t`Inactive`}</span>}
                </div>
                {item.description && <div className={classes.itemDescription}>{item.description}</div>}
            </div>
            <div className={classes.rate}>{formatRate(item)}</div>
            {!isReadOnly && (
                <Menu shadow="md" width={180} position="bottom-end">
                    <Menu.Target>
                        <ActionIcon variant="subtle" color="gray" aria-label={t`Actions for ${item.name}`}>
                            <IconDots size={18}/>
                        </ActionIcon>
                    </Menu.Target>
                    <Menu.Dropdown>
                        <Menu.Item leftSection={<IconPencil size={14}/>} onClick={() => setEditing(item)}>
                            {t`Edit`}
                        </Menu.Item>
                        <Menu.Item color="red" leftSection={<IconTrash size={14}/>} onClick={() => handleDelete(item)}>
                            {t`Delete`}
                        </Menu.Item>
                    </Menu.Dropdown>
                </Menu>
            )}
        </div>
    );

    const renderContent = () => {
        if (isError && !taxesAndFees) {
            return (
                <SettingsSection>
                    <div className={classes.empty}>
                        <div className={classes.emptyTitle}>{t`Couldn't load your taxes and fees`}</div>
                        <p className={classes.emptyText}>{t`Check your connection and try again.`}</p>
                        <Button variant="default" loading={isFetching} onClick={() => refetch()}>
                            {t`Try again`}
                        </Button>
                    </div>
                </SettingsSection>
            );
        }

        if (!isFetched) {
            return (
                <SettingsSection>
                    <Center py="xl"><Loader size="sm"/></Center>
                </SettingsSection>
            );
        }

        if (items.length === 0) {
            return (
                <SettingsSection>
                    <div className={classes.empty}>
                        <div className={classes.emptyTitle}>{t`No taxes or fees yet`}</div>
                        <p className={classes.emptyText}>
                            {t`Set up VAT, sales tax or a booking fee once, then add it to any product.`}
                        </p>
                        {!isReadOnly && (
                            <Button variant="default" leftSection={<IconPlus size={16}/>} onClick={openCreateModal}>
                                {t`Add tax or fee`}
                            </Button>
                        )}
                    </div>
                </SettingsSection>
            );
        }

        return (
            <>
                {taxes.length > 0 && (
                    <SettingsSection title={t`Taxes`}>{taxes.map(renderItem)}</SettingsSection>
                )}
                {fees.length > 0 && (
                    <SettingsSection title={t`Fees`}>{fees.map(renderItem)}</SettingsSection>
                )}
            </>
        );
    };

    return (
        <SettingsPage
            title={t`Taxes and fees`}
            description={t`Rates you can add to products. They're shared by every organizer and event in this account.`}
            actions={items.length > 0 && !isReadOnly && (
                <Button leftSection={<IconPlus size={16}/>} onClick={openCreateModal} data-testid="tax-or-fee-add-button">
                    {t`Add tax or fee`}
                </Button>
            )}
        >
            {renderContent()}

            {createModalOpen && <CreateTaxOrFeeModal onClose={closeCreateModal}/>}
            {editing && <EditTaxOrFeeModal taxOrFee={editing} onClose={() => setEditing(null)}/>}
        </SettingsPage>
    );
};

export default TaxesAndFees;
