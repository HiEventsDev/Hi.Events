import React from 'react';
import {applyUserGeneratedLinkSafety} from "../../../utilites/userGeneratedHtml";

interface UserGeneratedContentProps extends React.HTMLAttributes<HTMLDivElement> {
    html?: string | null;
}

export const UserGeneratedContent = ({html, dangerouslySetInnerHTML, ...props}: UserGeneratedContentProps) => {
    const source = html ?? dangerouslySetInnerHTML?.__html ?? '';

    return (
        <div
            {...props}
            dangerouslySetInnerHTML={{__html: applyUserGeneratedLinkSafety(String(source))}}
        />
    );
};
