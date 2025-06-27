import {GenericDataResponse, IdParam, Image, ImageType} from "../types.ts";
import {api} from "./client.ts";

export const imageClient = {
    uploadImage: async (image: File, imageType?: ImageType, entityId?: IdParam) => {
        const formData = new FormData();
        formData.append('image', image);
        if (imageType) {
            formData.append('image_type', imageType);
        }
        if (entityId) {
            formData.append('entity_id', entityId as string);
        }
        const response = await api.post<GenericDataResponse<Image>>('images', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        return response.data;
    },
    delete: async (imageId: IdParam) => {
        const response = await api.delete(`images/${imageId}`);
        return response.data;
    },
}
