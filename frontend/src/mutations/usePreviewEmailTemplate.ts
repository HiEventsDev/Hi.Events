import {useMutation} from "@tanstack/react-query";
import {emailTemplateClient} from "../api/email-template.client";
import {PreviewEmailTemplateRequest, IdParam} from "../types";

export const usePreviewEmailTemplateForOrganizer = () => {
    return useMutation({
        mutationFn: ({organizerId, previewData}: {
            organizerId: IdParam;
            previewData: PreviewEmailTemplateRequest;
        }) => emailTemplateClient.previewForOrganizer(organizerId, previewData),
    });
};

export const usePreviewEmailTemplateForEvent = () => {
    return useMutation({
        mutationFn: ({eventId, previewData}: {
            eventId: IdParam;
            previewData: PreviewEmailTemplateRequest;
        }) => emailTemplateClient.previewForEvent(eventId, previewData),
    });
};