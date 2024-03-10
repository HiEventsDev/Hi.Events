import {useParams} from "react-router-dom";
import {PageBody} from "../../common/PageBody";
import {useGetEventQuestions} from "../../../queries/useGetEventQuestions.ts";
import {QuestionsTable} from "../../common/QuestionsTable";
import {TableSkeleton} from "../../common/TableSkeleton";

export const Questions = () => {
    const {eventId} = useParams();
    const questionQuery = useGetEventQuestions(eventId);
    const orderQuestions = questionQuery?.data;

    return (
        <PageBody>
            <TableSkeleton numRows={5} isVisible={!orderQuestions}/>
            {orderQuestions && <QuestionsTable questions={orderQuestions}/>}
        </PageBody>
    );
};

export default Questions;