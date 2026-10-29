import {useEffect, useState} from "react";

export const RESUME_PARAM = 'resume';

export const resumeUrl = (path: string, action: string) => `${path}?${RESUME_PARAM}=${encodeURIComponent(action)}`;

export const useResumeAction = (): string | null => {
    const [action, setAction] = useState<string | null>(null);

    useEffect(() => {
        const url = new URL(window.location.href);
        const resume = url.searchParams.get(RESUME_PARAM);

        if (!resume) {
            return;
        }

        url.searchParams.delete(RESUME_PARAM);
        window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash);
        setAction(resume);
    }, []);

    return action;
};
