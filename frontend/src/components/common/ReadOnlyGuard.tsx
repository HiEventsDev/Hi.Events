import React from 'react';
import {Navigate, useParams} from 'react-router';
import {useIsReadOnly} from '../../hooks/useIsCurrentUserAdmin';

interface ReadOnlyGuardProps {
    children: React.ReactNode;
    redirectPath?: string;
}

const ReadOnlyGuard: React.FC<ReadOnlyGuardProps> = ({children, redirectPath}) => {
    const isReadOnly = useIsReadOnly();
    const {organizerId, eventId} = useParams();

    if (isReadOnly) {
        // Default redirect to organizer or event dashboard
        const defaultRedirect = eventId 
            ? `/manage/event/${eventId}/dashboard` 
            : organizerId 
                ? `/manage/organizer/${organizerId}/dashboard`
                : '/manage/events';
                
        return <Navigate to={redirectPath || defaultRedirect} replace />;
    }

    return <>{children}</>;
};

export default ReadOnlyGuard;
