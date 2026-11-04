import {t} from "@lingui/macro";
import {Menu} from "@mantine/core";
import {IconPlus, IconTemplate} from "@tabler/icons-react";
import {QuestionRequestData} from "../../../../types.ts";
import {CreateButton} from "../../../common/CreateButton";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {getQuestionTypeOptions, isChoiceQuestionType} from "../../../forms/QuestionForm/questionTypes.ts";
import {getQuestionTemplates} from "./questionTemplates.ts";

interface AddQuestionMenuProps {
    onSelect: (values: Partial<QuestionRequestData>) => void;
    dataTestId: string;
}

export const AddQuestionMenu = ({onSelect, dataTestId}: AddQuestionMenuProps) => {
    const {isReadOnly} = useAccess();

    if (isReadOnly) {
        return null;
    }

    return (
        <Menu shadow="md" width={240} position="bottom-end">
            <Menu.Target>
                <CreateButton
                    size="xs"
                    variant="light"
                    leftSection={<IconPlus size={14}/>}
                    data-testid={dataTestId}
                >
                    {t`Add question`}
                </CreateButton>
            </Menu.Target>

            <Menu.Dropdown mah={420} style={{overflowY: 'auto'}}>
                <Menu.Label>{t`Answer type`}</Menu.Label>
                {getQuestionTypeOptions().map(({type, label, Icon}) => (
                    <Menu.Item
                        key={type}
                        leftSection={<Icon size={16} stroke={1.5}/>}
                        onClick={() => onSelect({type, options: isChoiceQuestionType(type) ? ['', ''] : []})}
                        data-testid={`question-type-option-${type}`}
                    >
                        {label}
                    </Menu.Item>
                ))}

                <Menu.Divider/>
                <Menu.Label>{t`Templates`}</Menu.Label>
                {getQuestionTemplates().map((template) => (
                    <Menu.Item
                        key={template.id}
                        leftSection={<IconTemplate size={16} stroke={1.5}/>}
                        onClick={() => onSelect(template.values)}
                        data-testid={`question-template-${template.id}`}
                    >
                        {template.label}
                    </Menu.Item>
                ))}
            </Menu.Dropdown>
        </Menu>
    );
};
