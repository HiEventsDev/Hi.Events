import React, {useEffect, useRef} from 'react';

interface UserGeneratedContentProps extends React.HTMLAttributes<HTMLDivElement> {
}

export const UserGeneratedContent = (props: UserGeneratedContentProps) => {
    const contentRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (contentRef.current) {
            const anchors = contentRef.current.querySelectorAll<HTMLAnchorElement>('a');
            anchors.forEach(anchor => {
                anchor.setAttribute('rel', 'nofollow noopener noreferrer ugc');
                anchor.setAttribute('target', '_blank');
            });
        }
    }, [props.children]);

    return <div ref={contentRef} {...props} />;
};