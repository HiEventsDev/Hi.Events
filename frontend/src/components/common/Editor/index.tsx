import {InputLabel, Skeleton} from "@mantine/core";
import {prefetchOnIdle} from "../../../utilites/helpers.ts";
import {createLazyModule} from "../../../utilites/lazyModule.ts";
import type {EditorProps} from "./Editor";
import classes from "./Editor.module.scss";
import classNames from "classnames";

const editorModule = createLazyModule(() => import("./Editor"));

prefetchOnIdle(editorModule.load);

const EditorFallback = ({label, required, size = 'md', className = ''}: EditorProps) => (
    <div className={classNames([classes.inputWrapper, className])}>
        {label && <InputLabel size={size} required={required}>{label}</InputLabel>}
        <Skeleton height={100} radius="sm"/>
    </div>
);

export const Editor = (props: EditorProps) => {
    const module = editorModule.useModule();

    if (!module) {
        return <EditorFallback {...props}/>;
    }

    return <module.Editor {...props}/>;
};
