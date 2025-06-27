import {t} from "@lingui/macro";
import {useParams} from "react-router";
import {ImageUploadDropzone} from "../../../../../common/ImageUploadDropzone";
import {Card} from "../../../../../common/Card";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {useGetOrganizer} from "../../../../../../queries/useGetOrganizer.ts";

const Settings = () => {
    const {organizerId} = useParams();
    const organizerQuery = useGetOrganizer(organizerId);
    const {data: organizer} = organizerQuery;
    const organizerLogo = organizer?.images?.find((image) => image.type === 'ORGANIZER_LOGO');
    const organizerCover = organizer?.images?.find((image) => image.type === 'ORGANIZER_COVER');

    const handleImageChange = () => {
        organizerQuery.refetch();
    };

    return (
        <>
            <Card>
                <HeadingWithDescription
                    heading={t`Logo & Cover`}
                    description={t`Logo and cover image for your organizer`}
                />
                <h2>{t`Logo`}</h2>
                <ImageUploadDropzone
                    helpText={t`Upload a logo for your organizer`}
                    imageType={'ORGANIZER_LOGO'}
                    entityId={organizerId}
                    onUploadSuccess={handleImageChange}
                    existingImageData={{
                        url: organizerLogo?.url,
                        id: organizerLogo?.id,
                    }}
                />
                <h2>{t`Cover`}</h2>
                <ImageUploadDropzone
                    helpText={t`Upload a cover image for your organizer`}
                    imageType={'ORGANIZER_COVER'}
                    entityId={organizerId}
                    onUploadSuccess={handleImageChange}
                    existingImageData={{
                        url: organizerCover?.url,
                        id: organizerCover?.id,
                    }}
                />
            </Card>
        </>
    );
}

export default Settings;
