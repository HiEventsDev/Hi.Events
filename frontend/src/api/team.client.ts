import {api} from "./client";
import {GenericDataResponse, IdParam, TeamMemberAccess} from "../types";

export type TeamScope = { type: 'event' | 'organizer', id: IdParam };

export const teamClient = {
    members: async (scope: TeamScope) => {
        const path = scope.type === 'event' ? `events/${scope.id}/team` : `organizers/${scope.id}/team`;
        const response = await api.get<GenericDataResponse<TeamMemberAccess[]>>(path);
        return response.data;
    },
};
