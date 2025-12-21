import {useQuery} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";

export const useGetAllConfigurations = () => {
    return useQuery({
        queryKey: ['admin', 'configurations'],
        queryFn: () => adminClient.getAllConfigurations(),
    });
};
