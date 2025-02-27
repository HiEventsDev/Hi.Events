import React from 'react';
import {IconEyeOff, IconPencil, IconPlus, IconTrash, IconTrashOff} from "@tabler/icons-react";
import classes from "../ProductsTable.module.scss";
import classNames from "classnames";
import {ActionIcon, Popover} from "@mantine/core";
import {useDisclosure} from "@mantine/hooks";
import {EditProductCategoryModal} from "../../../modals/EditProductCategoryModal";
import {ProductCategory} from "../../../../types.ts";
import {t} from "@lingui/macro";
import {useDeleteProductCategory} from "../../../../mutations/useDeleteProductCategory.ts";
import {useParams} from "react-router";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {SortArrows} from "../../SortArrows";
import {useSortProducts} from "../../../../mutations/useSortProducts.ts";

interface SortableCategoryProps {
    category: ProductCategory;
    children: React.ReactNode;
    isLastCategory: boolean;
    openCreateModal: () => void;
    categories: ProductCategory[];
}

export const SortableCategory: React.FC<SortableCategoryProps> = ({
                                                                      category,
                                                                      children,
                                                                      isLastCategory,
                                                                      openCreateModal,
                                                                      categories,
                                                                  }) => {
    const [isEditModalOpen, editModal] = useDisclosure(false);
    const {eventId} = useParams();
    const deleteMutation = useDeleteProductCategory();
    const sortMutation = useSortProducts();
    const upSortEnabled = categories.findIndex(cat => cat.id === category.id) > 0;
    const downSortEnabled = categories.findIndex(cat => cat.id === category.id) < categories.length - 1;

    const handleDelete = () => {
        if (isLastCategory) {
            showError(t`You cannot delete the last category.`);
            return;
        }

        deleteMutation.mutate({productCategoryId: category.id, eventId: eventId}, {
            onSuccess: () => {
                editModal.close();
            },
            onError: (error) => {
                if (error?.response?.status && error.response.status === 409 && error?.response?.data?.message) {
                    showError(error?.response?.data.message);
                    return;
                } else {
                    showError(t`We couldn't delete the category. Please try again.`);
                }
            }
        });
    }

    const handleSort = (direction: 'up' | 'down') => {
        if (!eventId || !category.id) return;

        const currentIndex = categories.findIndex(cat => cat.id === category.id);

        if (currentIndex === -1) return;

        const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;

        if (targetIndex < 0 || targetIndex >= categories.length) return;

        const newCategories = [...categories];
        const [movedCategory] = newCategories.splice(currentIndex, 1);
        newCategories.splice(targetIndex, 0, movedCategory);

        // Prepare the sorted categories data
        const sortedCategories = newCategories.map(cat =>
            ({
                product_category_id: cat.id as number,
                sorted_products: (cat.products || []).map((product, index) => ({
                    id: product.id as number,
                    sort_order: index
                }))
            }));

        sortMutation.mutate(
            {
                eventId: eventId,
                sortedCategories: sortedCategories
            },
            {
                onSuccess: () => {
                    showSuccess(t`Categories reordered successfully.`);
                },
                onError: () => {
                    showError(t`We couldn't reorder the categories. Please try again.`);
                }
            }
        );
    };

    return (
        <>
            <div className={classNames(classes.sortableCategory)}>
                <div className={classes.categoryHeader}>
                    <h2 className={classes.categoryTitle}>
                        {category.name}
                        {category.is_hidden && (
                            <Popover>
                                <Popover.Target>
                                    <IconEyeOff style={{cursor: 'pointer'}} size={14}/>
                                </Popover.Target>
                                <Popover.Dropdown>
                                    {t`This category is hidden from public view`}
                                </Popover.Dropdown>
                            </Popover>
                        )}
                    </h2>

                    <div className={classes.categoryActions}>
                        <SortArrows
                            upArrowEnabled={upSortEnabled}
                            downArrowEnabled={downSortEnabled}
                            onSortUp={() => handleSort('up')}
                            onSortDown={() => handleSort('down')}
                        />
                        <ActionIcon
                            className={classes.categoryAction}
                            onClick={openCreateModal}
                            title={t`Create category`}
                            aria-label={t`Create category`}
                            variant={'transparent'}
                        >
                            <IconPlus size={20}/>
                        </ActionIcon>
                        <ActionIcon
                            className={classes.categoryAction}
                            onClick={editModal.open}
                            title={t`Edit category`}
                            aria-label={t`Edit category`}
                            variant={'transparent'}
                        >
                            <IconPencil size={20}/>
                        </ActionIcon>
                        <ActionIcon
                            className={classes.categoryAction}
                            onClick={handleDelete}
                            title={t`Delete category`}
                            aria-label={t`Delete category`}
                            variant={'transparent'}
                        >
                            {isLastCategory ? <IconTrashOff size={20}/> : <IconTrash size={20}/>}
                        </ActionIcon>
                    </div>
                </div>
                <div className={classes.categoryContent}>
                    {children}
                </div>
            </div>
            {isEditModalOpen && (
                <EditProductCategoryModal
                    productCategoryId={category.id}
                    onClose={editModal.close}
                />
            )}
        </>
    );
};
