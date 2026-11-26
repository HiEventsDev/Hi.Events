import {CSSProperties} from "react";
import {Tooltip} from "@mantine/core";
import {CrmTag} from "../../types.ts";
import classes from "./TagList.module.scss";

export const TagList = ({tags, limit}: { tags: CrmTag[], limit?: number }) => {
    const shown = limit === undefined ? tags : tags.slice(0, limit);
    const hidden = tags.slice(shown.length);

    return (
        <span className={classes.list} data-single-line={limit !== undefined || undefined}>
            {shown.map(tag => (
                <span
                    key={tag.id}
                    className={classes.tag}
                    data-colored={tag.color ? true : undefined}
                    style={tag.color ? {'--tag-color': tag.color} as CSSProperties : undefined}
                >
                    <span>{tag.name}</span>
                </span>
            ))}
            {hidden.length > 0 && (
                <Tooltip label={hidden.map(tag => tag.name).join(', ')} multiline maw={260}>
                    <span className={`${classes.tag} ${classes.more}`}>+{hidden.length}</span>
                </Tooltip>
            )}
        </span>
    );
};
