import {GenericDataResponse, GenericPaginatedResponse, IdParam, Image, ImageType, QueryFilters} from "../types.ts";
import {api} from "./client.ts";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";

export const imageClient = {
    getAll: async (queryFilters: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<Image>>(
            'images' + queryParamsHelper.buildQueryString(queryFilters),
        );
        return response.data;
    },
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
