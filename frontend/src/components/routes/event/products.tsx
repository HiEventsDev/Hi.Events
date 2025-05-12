import {useParams} from "react-router";
import {useDisclosure} from "@mantine/hooks";
import {Button, Menu} from "@mantine/core";
import {IconCategory, IconChevronDown, IconPlus, IconShoppingCart} from "@tabler/icons-react";
import {PageTitle} from "../../common/PageTitle";
import {PageBody} from "../../common/PageBody";
import {CreateProductModal} from "../../modals/CreateProductModal";
import {ProductCategoryList} from "../../common/ProductsTable";
import {ToolBar} from "../../common/ToolBar";
import {TableSkeleton} from "../../common/TableSkeleton";
import {t} from "@lingui/macro";
import {useUrlHash} from "../../../hooks/useUrlHash.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetEventProductCategories} from "../../../queries/useGetProductCategories.ts";
import {SearchBar} from "../../common/SearchBar";
import {useState} from "react";
import {CreateProductCategoryModal} from "../../modals/CreateProductCategoryModal";
import {IdParam} from "../../../types.ts";

export const Products = () => {
    const [createProductModalOpen, {
        open: openCreateProductModal,
        close: closeCreateProductModal
    }] = useDisclosure(false);
    const [createProductCategoryModalOpen, {
        open: openCreateProductCategoryModal,
        close: closeCreateProductCategoryModal
    }] = useDisclosure(false);
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedCategoryId, setSelectedCategoryId] = useState<IdParam>(null);

    const productCategoriesQuery = useGetEventProductCategories(eventId);
    const productCategories = productCategoriesQuery?.data?.data;

    useUrlHash('create-product', () => openCreateProductModal());

    const openCreateProduct = (categoryId: IdParam) => {
        setSelectedCategoryId(() => categoryId);
        openCreateProductModal();
    }

    return (
        <PageBody>
            <PageTitle>{t`Tickets & Products`}</PageTitle>

            <ToolBar
                searchComponent={() => (
                    <SearchBar
                        onClear={() => setSearchTerm('')}
                        placeholder={t`Search products`}
                        rightSection={<IconChevronDown/>}
                        value={searchTerm}
                        onChange={(event) => setSearchTerm(event.target.value)}
                    />
                )}
            >
                <Menu
                    transitionProps={{transition: 'pop-top-right'}}
                    position="bottom"
                    width={220}
                    withinPortal
                >
                    <Menu.Target>
                        <Button
                            leftSection={<IconPlus/>}
                            color={'green'}
                            rightSection={
                                <IconChevronDown stroke={1.5}/>
                            }
                            pr={12}
                        >
                            {t`Create`}
                        </Button>
                    </Menu.Target>
                    <Menu.Dropdown>
                        <Menu.Item
                            leftSection={
                                <IconShoppingCart
                                    stroke={1.5}
                                />
                            }
                            onClick={() => openCreateProduct(undefined)}
                        >
                            {t`Ticket or Product`}
                        </Menu.Item>
                        <Menu.Item
                            leftSection={
                                <IconCategory
                                    stroke={1.5}
                                />
                            }
                            onClick={openCreateProductCategoryModal}
                        >
                            {t`Category`}
                        </Menu.Item>
                    </Menu.Dropdown>
                </Menu>
            </ToolBar>

            <TableSkeleton isVisible={!productCategories || !event}/>

            {(event && productCategories)
                && (<ProductCategoryList
                        initialCategories={productCategories}
                        event={event}
                        searchTerm={searchTerm}
                        onCreateOpen={openCreateProduct}
                    />
                )}

            {createProductModalOpen &&
                <CreateProductModal selectedCategoryId={selectedCategoryId} onClose={closeCreateProductModal}
                                    isOpen={createProductModalOpen}/>}
            {createProductCategoryModalOpen && <CreateProductCategoryModal onClose={closeCreateProductCategoryModal}
                                                                           isOpen={createProductCategoryModalOpen}/>}
        </PageBody>
    );
};

export default Products;
