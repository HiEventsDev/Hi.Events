import {t, Trans} from "@lingui/macro";
import {Anchor, UnstyledButton} from "@mantine/core";
import {useDisclosure} from "@mantine/hooks";
import {IconHelpCircle, IconMapPin, IconTicket, IconUsers} from "@tabler/icons-react";
import {ReactNode} from "react";
import {Link, useParams} from "react-router";
import {Modal} from "../Modal";
import classes from "./CapacityHelp.module.scss";

interface LedgerRow {
    label: string;
    value: string;
    muted?: boolean;
    indent?: boolean;
}

interface Scenario {
    title: string;
    situation: string;
    rows: LedgerRow[];
    sellable: string;
    takeaway: string;
}

const buildScenarios = (): Scenario[] => [
    {
        title: t`Yoga class`,
        situation: t`12 mats in the studio and one Drop-in ticket.`,
        rows: [
            {label: t`Capacity`, value: '12'},
            {label: t`Drop-in`, value: t`Unlimited`, indent: true, muted: true},
        ],
        sellable: '12',
        takeaway: t`Set capacity 12 on your dates. On the Tickets & Products page, leave the Drop-in ticket's quantity empty.`,
    },
    {
        title: t`Workshop with early bird`,
        situation: t`The room holds 20. Early bird is limited to 5 per date, Standard to 15.`,
        rows: [
            {label: t`Capacity`, value: '20'},
            {label: t`Early bird`, value: '5', indent: true, muted: true},
            {label: t`Standard`, value: '15', indent: true, muted: true},
        ],
        sellable: '20',
        takeaway: t`On the Tickets & Products page, set Early bird to 5 and Standard to 15, both "per date". Set capacity 20 on your dates. Raise Standard to 25 and the date still stops at 20.`,
    },
    {
        title: t`Theatre with sections`,
        situation: t`Stalls 100, Circle 50 and Balcony 30 per performance. No capacity set.`,
        rows: [
            {label: t`Capacity`, value: t`Unlimited`, muted: true},
            {label: t`Stalls`, value: '100', indent: true, muted: true},
            {label: t`Circle`, value: '50', indent: true, muted: true},
            {label: t`Balcony`, value: '30', indent: true, muted: true},
        ],
        sellable: '180',
        takeaway: t`On the Tickets & Products page, give each section its quantity "per date". Leave capacity empty and the sections add up to the total.`,
    },
    {
        title: t`Class with a T-shirt add-on`,
        situation: t`25 places per date, plus 30 T-shirts across the whole run.`,
        rows: [
            {label: t`Capacity`, value: '25'},
            {label: t`General admission`, value: '25', indent: true, muted: true},
            {label: t`T-shirt`, value: t`Not counted`, indent: true, muted: true},
        ],
        sellable: '25',
        takeaway: t`Only tickets count toward capacity. The T-shirt is a product, so its quantity is just stock and never limits the date.`,
    },
];

const ConceptCard = ({icon, title, where, children}: { icon: ReactNode; title: string; where: ReactNode; children: ReactNode }) => (
    <div className={classes.concept}>
        <div className={classes.conceptIcon}>{icon}</div>
        <div>
            <div className={classes.conceptTitle}>{title}</div>
            <div className={classes.conceptBody}>{children}</div>
            <div className={classes.conceptWhere}>
                <IconMapPin size={13}/>
                <span>{where}</span>
            </div>
        </div>
    </div>
);

const ScenarioCard = ({scenario}: { scenario: Scenario }) => (
    <div className={classes.scenario} data-testid="capacity-help-scenario">
        <div className={classes.scenarioTitle}>{scenario.title}</div>
        <div className={classes.scenarioSituation}>{scenario.situation}</div>
        <div className={classes.ledger}>
            {scenario.rows.map((row) => (
                <div key={row.label} className={`${classes.ledgerRow} ${row.indent ? classes.ledgerIndent : ''} ${row.muted ? classes.ledgerMuted : ''}`}>
                    <span>{row.label}</span>
                    <span className={classes.ledgerValue}>{row.value}</span>
                </div>
            ))}
            <div className={classes.ledgerTotal}>
                <span>{t`Sellable per date`}</span>
                <span className={classes.ledgerValue}>{scenario.sellable}</span>
            </div>
        </div>
        <div className={classes.takeaway}>{scenario.takeaway}</div>
    </div>
);

const CapacityHelpModal = ({onClose}: { onClose: () => void }) => {
    const {eventId} = useParams();

    return (
        <Modal opened onClose={onClose} heading={t`How capacity works`}>
            <div className={classes.lead}>
                {t`Two settings decide how many people can book a date. Whichever is lower is what you can sell.`}
            </div>

            <div className={classes.concepts}>
                <ConceptCard
                    icon={<IconUsers size={18}/>}
                    title={t`Capacity`}
                    where={(
                        <Trans>
                            Set it when you add dates, or on any single date under{" "}
                            <Anchor component={Link} to={`/manage/event/${eventId}/occurrences`} inherit>Occurrence Schedule</Anchor>.
                        </Trans>
                    )}
                >
                    {t`The size of the room. The total number of people who can attend a date across every ticket. Leave it empty for no limit.`}
                </ConceptCard>
                <ConceptCard
                    icon={<IconTicket size={18}/>}
                    title={t`Ticket allocation`}
                    where={(
                        <Trans>
                            Set it in each ticket's Quantity field on{" "}
                            <Anchor component={Link} to={`/manage/event/${eventId}/products`} inherit>Tickets &amp; Products</Anchor>,
                            when you create or edit the ticket.
                        </Trans>
                    )}
                >
                    {t`How many of each ticket you sell on a date. Next to the quantity, choose "per date" to allocate it to every date, or "all dates" to share one pool across the whole run.`}
                </ConceptCard>
            </div>

            <div className={classes.sectionLabel}>{t`Scenarios`}</div>
            <div className={classes.scenarios}>
                {buildScenarios().map((scenario) => <ScenarioCard key={scenario.title} scenario={scenario}/>)}
            </div>

            <div className={classes.sectionLabel}>{t`Good to know`}</div>
            <ul className={classes.notes}>
                <li>{t`Only tickets count toward capacity. Products such as merchandise or parking never do.`}</li>
                <li>{t`Any single date can differ: change its capacity in Edit Date, or a ticket's per-date quantity on that date's Products tab.`}</li>
                <li>{t`Tickets set to "all dates" share one pool across the run. They show as "All dates · shared" in a date's breakdown and only capacity limits them per date.`}</li>
            </ul>
        </Modal>
    );
};

export const CapacityHelpLink = () => {
    const [opened, {open, close}] = useDisclosure(false);

    return (
        <>
            <UnstyledButton className={classes.link} onClick={open} data-testid="capacity-help-link">
                <IconHelpCircle size={14}/>
                {t`How capacity works`}
            </UnstyledButton>
            {opened && <CapacityHelpModal onClose={close}/>}
        </>
    );
};
