import {useState} from "react";
import {t} from "@lingui/macro";
import {Loader, Stack, Text, TextInput} from "@mantine/core";
import {IconSearch} from "@tabler/icons-react";
import {useDebouncedValue} from "@mantine/hooks";
import {useGetAccountImages} from "../../../../../../queries/useGetAccountImages.ts";
import {Pagination} from "../../../../Pagination";
import {Image as ImageType} from "../../../../../../types.ts";
import classes from './index.module.scss';

interface BrowseImagesPanelProps {
    onImageSelected: (url: string) => void;
    selectedUrl: string | null;
}

export const BrowseImagesPanel = ({onImageSelected, selectedUrl}: BrowseImagesPanelProps) => {
    const [page, setPage] = useState(1);
    const [searchQuery, setSearchQuery] = useState('');
    const [debouncedQuery] = useDebouncedValue(searchQuery, 300);

    const {data, isLoading} = useGetAccountImages({
        pageNumber: page,
        perPage: 24,
        query: debouncedQuery || undefined,
        sortBy: 'created_at',
        sortDirection: 'desc',
    });

    const handleSearchChange = (value: string) => {
        setSearchQuery(value);
        setPage(1);
    };

    return (
        <Stack>
            <TextInput
                placeholder={t`Search by filename...`}
                leftSection={<IconSearch size={16}/>}
                value={searchQuery}
                onChange={(e) => handleSearchChange(e.currentTarget.value)}
            />

            {isLoading ? (
                <Stack align="center" py="xl">
                    <Loader size="lg"/>
                </Stack>
            ) : data?.data && data.data.length > 0 ? (
                <>
                    <div className={classes.imageGrid}>
                        {data.data.map((image: ImageType) => (
                            <div key={image.id}>
                                <div
                                    className={`${classes.thumbnail} ${selectedUrl === image.url ? classes.thumbnailSelected : ''}`}
                                    onClick={() => onImageSelected(image.url)}
                                >
                                    <img
                                        src={image.url}
                                        alt={image.file_name}
                                        loading="lazy"
                                        style={image.lqip_base64 ? {
                                            backgroundImage: `url(${image.lqip_base64})`,
                                            backgroundSize: 'cover',
                                        } : undefined}
                                    />
                                </div>
                                <div className={classes.fileName} title={image.file_name}>
                                    {image.file_name}
                                </div>
                            </div>
                        ))}
                    </div>
                    <Pagination
                        total={data.meta.last_page}
                        value={page}
                        onChange={setPage}
                        marginTop={0}
                    />
                </>
            ) : (
                <Text c="dimmed" ta="center" py="xl" size="sm">
                    {debouncedQuery
                        ? t`No images matching "${debouncedQuery}"`
                        : t`No images yet. Upload an image using the Upload tab.`}
                </Text>
            )}
        </Stack>
    );
};
