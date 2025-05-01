import {GenericDataResponse, Image} from "../types.ts";
import {api} from "./client.ts";

export const imageClient = {
    uploadImage: async (image: File) => {
        const formData = new FormData();
        formData.append('image', image);
        const response = await api.post<GenericDataResponse<Image>>('images', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        return response.data;
    },
}
