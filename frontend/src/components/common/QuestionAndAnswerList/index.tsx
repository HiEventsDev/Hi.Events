import {Card} from "../Card";
import {QuestionAnswer} from "../../../types.ts";

interface QuestionAndAnswerListProps {
    questionAnswers: QuestionAnswer[]
}

export const QuestionAndAnswerList = ({questionAnswers}: QuestionAndAnswerListProps) => {
    return (
        <Card variant={'lightGray'}>
            {questionAnswers.map((answer, index) => (
                <div key={index}>
                    <strong>{answer.title}</strong>
                    <p>
                        {answer.text_answer}
                    </p>
                </div>
            ))}
        </Card>
    );
}