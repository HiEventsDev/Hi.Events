import {useParams} from "react-router";
import {PageBody} from "../../common/PageBody";
import {useGetEventQuestions} from "../../../queries/useGetEventQuestions.ts";
import {TableSkeleton} from "../../common/TableSkeleton";
import {QuestionsBuilder} from "./Questions/QuestionsBuilder.tsx";

export const Questions = () => {
    const {eventId} = useParams();
    const questionQuery = useGetEventQuestions(eventId);
    const questions = questionQuery?.data;

    return (
        <PageBody>
            <TableSkeleton numRows={5} isVisible={!questions}/>
            {questions && <QuestionsBuilder questions={questions}/>}
        </PageBody>
    );
};

export default Questions;
