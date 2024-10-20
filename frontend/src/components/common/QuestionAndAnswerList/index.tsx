import { Card } from "../Card";
import { QuestionAnswer } from "../../../types.ts";
import { Table } from '@mantine/core';

interface QuestionAndAnswerListProps {
    questionAnswers: QuestionAnswer[];
    belongsToFilter?: string[];  // Array of filter values
}

export const QuestionAndAnswerList = ({ questionAnswers, belongsToFilter }: QuestionAndAnswerListProps) => {
    // Filter questionAnswers by 'belongs_to' array if the filter is applied
    const filteredQuestions = belongsToFilter && belongsToFilter.length > 0
        ? questionAnswers.filter(qa => belongsToFilter.includes(qa.belongs_to))
        : questionAnswers;

    // Categorize the questions
    const productQuestions = filteredQuestions.filter(qa => qa.belongs_to === 'PRODUCT' && !qa.attendee_id);
    const attendeeQuestions = filteredQuestions.filter(qa => qa.belongs_to === 'PRODUCT' && qa.attendee_id);
    const orderQuestions = filteredQuestions.filter(qa => qa.belongs_to === 'ORDER');

    // Function to render a table for a given category of questions
    const renderTable = (title: string, questions: QuestionAnswer[], showProductColumn = true) => (
        <Card variant={'lightGray'} style={{ marginBottom: '1rem' }}>
            <h3 style={{ textAlign: 'left', marginBottom: '1rem', marginTop: 0 }}>{title}</h3>
            {questions.length > 0 ? (
                <Table striped highlightOnHover withBorder withColumnBorders>
                    <thead>
                    <tr>
                        {showProductColumn && <th style={{ textAlign: 'left' }}>Product</th>}
                        <th style={{ textAlign: 'left' }}>Question</th>
                        <th style={{ textAlign: 'left' }}>Answer</th>
                        {title === "Attendee Answers" && <th style={{ textAlign: 'left' }}>Attendee</th>}
                    </tr>
                    </thead>
                    <tbody>
                    {questions.map((qa, index) => (
                        <tr key={index}>
                            {showProductColumn && <td>{qa.product_title || 'N/A'}</td>}
                            <td>{qa.title}</td>
                            <td>{Array.isArray(qa.answer) ? qa.answer.join(", ") : qa.answer}</td>
                            {title === "Attendee Answers" && (
                                <td>{qa.first_name ? `${qa.first_name} ${qa.last_name}` : 'N/A'}</td>
                            )}
                        </tr>
                    ))}
                    </tbody>
                </Table>
            ) : (
                <p>No {title.toLowerCase()} questions available.</p>
            )}
        </Card>
    );

    return (
        <div>
            {renderTable('Attendee Answers', attendeeQuestions)}
            {renderTable('Order Answers', orderQuestions, false)}
            {renderTable('Products Answers', productQuestions)}
        </div>
    );
}
