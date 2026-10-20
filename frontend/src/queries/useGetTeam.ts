import {useQuery} from "@tanstack/react-query";
import {teamClient, TeamScope} from "../api/team.client.ts";

export const GET_TEAM_QUERY_KEY = 'getTeam';

export const useGetTeam = (scope: TeamScope, enabled = true) => {
    return useQuery({
        queryKey: [GET_TEAM_QUERY_KEY, scope.type, String(scope.id)],
        queryFn: async () => await teamClient.members(scope),
        enabled: enabled && !!scope.id,
    });
};
