import { QueryClient } from "@tanstack/react-query";

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60,
      refetchOnWindowFocus: false,
      retry: (failureCount, error: any) => ![401, 403].includes(error?.response?.status) && failureCount < 3,
      networkMode: "always",
    },
    mutations: {
      networkMode: 'always',
    }
  },
});
