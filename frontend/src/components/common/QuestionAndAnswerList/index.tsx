import {QuestionAnswer} from "../../../types.ts";
import {ActionIcon, Table, Tooltip} from '@mantine/core';
import {t} from "@lingui/macro";
import {NavLink} from "react-router-dom";
import {IconExternalLink} from "@tabler/icons-react";

interface QuestionAndAnswerListProps {
    questionAnswers: QuestionAnswer[];
    belongsToFilter?: string[];  // Array of filter values
}

export const QuestionAndAnswerList = ({questionAnswers, belongsToFilter}: QuestionAndAnswerListProps) => {
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
        <div style={{marginBottom: '2rem'}}>
            <h3 style={{textAlign: 'left', marginBottom: '1rem', marginTop: 0}}>{title}</h3>
            {questions.length > 0 ? (
                <Table    withTableBorder >
                    <Table.Thead>
                        <Table.Tr>
                            {showProductColumn && <Table.Th style={{textAlign: 'left'}}>Product</Table.Th>}
                            <Table.Th style={{textAlign: 'left'}}>Question</Table.Th>
                            <Table.Th style={{textAlign: 'left'}}>Answer</Table.Th>
                            {title === "Attendee Answers" && <Table.Th style={{textAlign: 'left'}}>Attendee</Table.Th>}
                        </Table.Tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {questions.map((qa, index) => (
                            <Table.Tr key={index}>
                                {showProductColumn && <Table.Td>{qa.product_title || 'N/A'}</Table.Td>}
                                <Table.Td>{qa.title}</Table.Td>
                                <Table.Td>{Array.isArray(qa.answer) ? qa.answer.join(", ") : qa.answer}</Table.Td>
                                {title === "Attendee Answers" && (
                                    <Table.Td>
                                        {qa.first_name ? `${qa.first_name} ${qa.last_name}` : 'N/A'}
                                        <Tooltip label={t`Navigate to Attendee`} position={'bottom'} withArrow>
                                            <NavLink to={`../attendees?query=${qa.attendee_id}`}>
                                                <ActionIcon variant={'transparent'} radius={'m'}>
                                                    <IconExternalLink size={16}/>
                                                </ActionIcon>
                                            </NavLink>
                                        </Tooltip>
                                    </Table.Td>
                                )}
                            </Table.Tr>
                        ))}
                    </Table.Tbody>
                </Table>
            ) : (
                <p>No {title.toLowerCase()} questions available.</p>
            )}
        </div>
    );

    return (
        <div>
            {attendeeQuestions.length > 0 && renderTable('Attendee Answers', attendeeQuestions)}
            {orderQuestions.length > 0 && renderTable('Order Answers', orderQuestions, false)}
            {productQuestions.length > 0 && renderTable('Products Answers', productQuestions)}
        </div>
    );
}
