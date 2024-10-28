import React from 'react';
import {IconEyeOff, IconGripVertical, IconPencil, IconPlus, IconTrash, IconTrashOff} from "@tabler/icons-react";
import classes from "../ProductsTable.module.scss";
import classNames from "classnames";
import {ActionIcon, Popover} from "@mantine/core";
import {useDisclosure} from "@mantine/hooks";
import {EditProductCategoryModal} from "../../../modals/EditProductCategoryModal";
import {ProductCategory} from "../../../../types.ts";
import {t} from "@lingui/macro";
import {useDeleteProductCategory} from "../../../../mutations/useDeleteProductCategory.ts";
import {useParams} from "react-router-dom";
import {showError} from "../../../../utilites/notifications.tsx";
import {AxiosError} from "axios";

interface SortableCategoryProps {
    category: ProductCategory;
    children: React.ReactNode;
    isLastCategory: boolean;
    openCreateModal: () => void;
}

export const SortableCategory: React.FC<SortableCategoryProps> = ({
                                                                      category,
                                                                      children,
                                                                      isLastCategory,
                                                                      openCreateModal,
                                                                  }) => {
    const [isEditModalOpen, editModal] = useDisclosure(false);
    const {eventId} = useParams();
    const deleteMutation = useDeleteProductCategory();

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
                        <ActionIcon
                            className={classes.categoryAction}
                            onClick={openCreateModal}
                            title={'Create category'}
                            aria-label={'Create category'}
                            variant={'transparent'}
                        >
                            <IconPlus size={20}/>
                        </ActionIcon>
                        <ActionIcon
                            className={classes.categoryAction}
                            onClick={editModal.open}
                            title={'Edit category'}
                            aria-label={'Edit category'}
                            variant={'transparent'}
                        >
                            <IconPencil size={20}/>
                        </ActionIcon>
                        <ActionIcon
                            className={classes.categoryAction}
                            onClick={handleDelete}
                            title={'Delete category'}
                            aria-label={'Delete category'}
                            variant={'transparent'}
                        >
                            {isLastCategory ? <IconTrashOff size={20}/> : <IconTrash size={20}/>}
                        </ActionIcon>
                        <div className={classNames([
                            classes.dragHandle,
                            classes.categoryDragHandle,
                        ])}>
                            <IconGripVertical size={20}/>
                        </div>
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
