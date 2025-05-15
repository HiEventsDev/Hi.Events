import {useMutation, useQuery} from "@tanstack/react-query";
import {questionClient} from "../api/question.client";
import {useState} from "react";
import {t} from "@lingui/macro";
import {showError, showSuccess} from "../utilites/notifications.tsx";
import {downloadFile} from "../utilites/download.ts";
import {IdParam} from "../types.ts";

export const useExportAnswers = (eventId: IdParam) => {
    const [jobUuid, setJobUuid] = useState<string | null>(null);

    const startExportMutation = useMutation({
        mutationFn: async () => {
            const {job_uuid} = await questionClient.exportAnswers(eventId);
            if (!job_uuid) throw new Error(t`Failed to start export job`);
            setJobUuid(job_uuid);
            showSuccess(t`Export started. Preparing file...`);
            return job_uuid;
        },
    });

    const query = useQuery({
        queryKey: ["exportStatus", jobUuid],
        queryFn: async () => {
            if (!jobUuid) {
                return null;
            }

            try {
                const data = await questionClient.checkExportStatus(eventId, jobUuid);

                if (data.status === "FINISHED" && data.download_url) {
                    showSuccess(t`Exporting complete. Downloading file...`);
                    downloadFile(data.download_url as string, data.download_url.split("/").pop() as string);
                    setJobUuid(null);
                }

                if (data.status === "FAILED" || data.status === "NOT_FOUND") {
                    showError(t`Export failed. Please try again.`);
                    setJobUuid(null);
                }

                return data;
            } catch (error) {
                showError(t`An error occurred while checking export status.`);
                setJobUuid(null);
                return null;
            }
        },
        enabled: !!jobUuid,
        refetchInterval: (data) => {
            const status = data?.state?.data?.status;
            return (status === "IN_PROGRESS"
                    ? 5000
                    : false
            );
        },
    });

    return {
        startExport: startExportMutation.mutate,
        isExporting: startExportMutation.isPending || query.isFetching,
    };
};
