import {ReactNode, useEffect, useId} from "react";
import {Text} from "@mantine/core";
import {
    closestCenter,
    DndContext,
    KeyboardSensor,
    PointerSensor,
    TouchSensor,
    UniqueIdentifier,
    useSensor,
    useSensors,
} from "@dnd-kit/core";
import {SortableContext, sortableKeyboardCoordinates, verticalListSortingStrategy} from "@dnd-kit/sortable";
import {IdParam, Question} from "../../../../types.ts";
import {useDragItemsHandler} from "../../../../hooks/useDragItemsHandler.ts";
import {isChoiceQuestionType} from "../../../forms/QuestionForm/questionTypes.ts";
import {FollowUpRow, QuestionRowHandlers, SortableQuestionGroup} from "./QuestionRow.tsx";
import classes from "./QuestionsBuilder.module.scss";

const MAX_FOLLOW_UP_DEPTH = 5;

interface QuestionSectionProps extends QuestionRowHandlers {
    title: string;
    description: string;
    headerActions: ReactNode;
    intro: ReactNode;
    questions: Question[];
    questionsById: Map<number, Question>;
    emptyMessage: string;
    emptyHint: string;
    productTitles: Map<number, string>;
    dataTestId: string;
    onSortEnd: (sortedIds: IdParam[]) => void;
}

interface QuestionTree {
    roots: Question[];
    followUpsByParentId: Map<number, Question[]>;
}

const buildTree = (questions: Question[]): QuestionTree => {
    const sectionIds = new Set(questions.map((question) => Number(question.id)));
    const followUpsByParentId = new Map<number, Question[]>();
    const roots: Question[] = [];

    questions.forEach((question) => {
        const parentId = Number(question.condition_question_id);
        if (question.condition_question_id && sectionIds.has(parentId)) {
            followUpsByParentId.set(parentId, [...(followUpsByParentId.get(parentId) ?? []), question]);
        } else {
            roots.push(question);
        }
    });

    return {roots, followUpsByParentId};
};

const collectFollowUps = (
    parent: Question,
    tree: QuestionTree,
    depth = 1,
    visited = new Set<number>(),
): FollowUpRow[] => (tree.followUpsByParentId.get(Number(parent.id)) ?? []).flatMap((followUp) => {
    if (visited.has(Number(followUp.id))) {
        return [];
    }
    visited.add(Number(followUp.id));

    return [
        {
            question: followUp,
            depth,
            parent,
            canAddFollowUp: isChoiceQuestionType(followUp.type) && depth < MAX_FOLLOW_UP_DEPTH,
        },
        ...collectFollowUps(followUp, tree, depth + 1, visited),
    ];
});

const flattenWithFollowUps = (rootIds: IdParam[], questions: Question[]): IdParam[] => {
    const tree = buildTree(questions);
    const questionsById = new Map(questions.map((question) => [Number(question.id), question]));

    return rootIds.flatMap((rootId) => {
        const root = questionsById.get(Number(rootId));
        return root ? [rootId, ...collectFollowUps(root, tree).map((row) => row.question.id as IdParam)] : [];
    });
};

export const QuestionSection = ({
                                    title,
                                    description,
                                    headerActions,
                                    intro,
                                    questions,
                                    questionsById,
                                    emptyMessage,
                                    emptyHint,
                                    productTitles,
                                    dataTestId,
                                    onSortEnd,
                                    ...rowHandlers
                                }: QuestionSectionProps) => {
    const dndId = useId();
    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(TouchSensor),
        useSensor(KeyboardSensor, {coordinateGetter: sortableKeyboardCoordinates}),
    );
    const tree = buildTree(questions);
    const rootIds = tree.roots.map((question) => Number(question.id));
    const {items, setItems, handleDragEnd} = useDragItemsHandler({
        initialItemIds: rootIds,
        onSortEnd: (sortedRootIds) => onSortEnd(flattenWithFollowUps(sortedRootIds, questions)),
    });

    useEffect(() => {
        setItems(rootIds);
    }, [rootIds.join(',')]);

    const rootsById = new Map(tree.roots.map((question) => [Number(question.id), question]));
    const orderedRoots = items
        .map((id) => rootsById.get(Number(id)))
        .filter((question): question is Question => !!question);

    return (
        <section className={classes.section} data-testid={dataTestId}>
            <header className={classes.sectionHeader}>
                <div className={classes.sectionHeading}>
                    <h2 className={classes.sectionTitle}>{title}</h2>
                    <Text size="xs" c="dimmed">{description}</Text>
                </div>
                <div className={classes.sectionActions}>{headerActions}</div>
            </header>

            <div className={classes.sectionIntro}>{intro}</div>

            {orderedRoots.length === 0 ? (
                <div className={classes.emptyState}>
                    <Text size="sm" fw={500}>{emptyMessage}</Text>
                    <Text size="xs" c="dimmed">{emptyHint}</Text>
                </div>
            ) : (
                <DndContext id={dndId} sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
                    <SortableContext items={items as UniqueIdentifier[]} strategy={verticalListSortingStrategy}>
                        <div className={classes.rows}>
                            {orderedRoots.map((root) => (
                                <SortableQuestionGroup
                                    key={root.id}
                                    root={root}
                                    rootParent={root.condition_question_id ? questionsById.get(Number(root.condition_question_id)) : undefined}
                                    canAddFollowUp={isChoiceQuestionType(root.type)}
                                    followUps={collectFollowUps(root, tree)}
                                    productTitles={productTitles}
                                    {...rowHandlers}
                                />
                            ))}
                        </div>
                    </SortableContext>
                </DndContext>
            )}
        </section>
    );
};
