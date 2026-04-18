import {t} from "@lingui/macro";
import {useState} from "react";
import {Badge, Group, Stack, Tabs} from "@mantine/core";
import {IconAlertTriangle, IconLink, IconUserPlus} from "@tabler/icons-react";
import {UnlinkedAttendeesSubTab} from "./UnlinkedAttendeesSubTab";
import {UnmappedQuestionsSubTab} from "./UnmappedQuestionsSubTab";
import {ConflictsSubTab} from "./ConflictsSubTab";
import {BackfillHelpPopover} from "./BackfillHelpPanel";
import {useGetBackfillSummary} from "../../../../../queries/useGetBackfillSummary.ts";
import classes from "./BackfillTab.module.scss";

export const BackfillTab = () => {
    const [activeSubTab, setActiveSubTab] = useState<string | null>('attendees');

    const summaryQuery = useGetBackfillSummary();
    const summary = summaryQuery.data?.data;

    const renderBadge = (count: number | undefined, color: string) => {
        if (count === undefined) return null;
        return <Badge size="xs" color={count > 0 ? color : 'gray'} variant="light">{count}</Badge>;
    };

    return (
        <Stack gap="md">
            <Tabs value={activeSubTab} onChange={setActiveSubTab} variant="outline" classNames={{tab: classes.tab}}>
                <Group justify="space-between" align="center" mb="md" wrap="nowrap">
                    <Tabs.List>
                        <Tabs.Tab
                            value="attendees"
                            leftSection={<IconUserPlus size={14}/>}
                            rightSection={renderBadge(summary?.unlinked_attendees_count, 'blue')}
                        >
                            {t`New Contacts`}
                        </Tabs.Tab>
                        <Tabs.Tab
                            value="questions"
                            leftSection={<IconLink size={14}/>}
                            rightSection={renderBadge(summary?.unmapped_questions_count, 'blue')}
                        >
                            {t`New Questions`}
                        </Tabs.Tab>
                        <Tabs.Tab
                            value="conflicts"
                            leftSection={<IconAlertTriangle size={14}/>}
                            rightSection={renderBadge(summary?.conflicts_count, 'orange')}
                        >
                            {t`Different Answers`}
                        </Tabs.Tab>
                    </Tabs.List>
                    <BackfillHelpPopover/>
                </Group>

                <Tabs.Panel value="attendees">
                    <UnlinkedAttendeesSubTab/>
                </Tabs.Panel>
                <Tabs.Panel value="questions">
                    <UnmappedQuestionsSubTab/>
                </Tabs.Panel>
                <Tabs.Panel value="conflicts">
                    <ConflictsSubTab/>
                </Tabs.Panel>
            </Tabs>
        </Stack>
    );
};
