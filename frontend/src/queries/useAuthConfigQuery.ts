import { useQuery } from '@tanstack/react-query';
import { authClient } from '../api/auth.client.ts';

export const useAuthConfigQuery = () => {
    return useQuery({
        queryKey: ['auth-config'],
        queryFn: async () => {
            const data = await authClient.getConfig();
            return data;
        },
        staleTime: 1000 * 60 * 60, // Cache for an hour since it shouldn't change
        retry: 2,
    });
};
