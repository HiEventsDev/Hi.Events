import {t} from "@lingui/macro";
import {Anchor, Button, Grid, Group, NumberInput, SegmentedControl, Stack, Text, Title} from "@mantine/core";
import {useEffect, useRef, useState} from "react";
import {Card} from "../Card";
import {HeadingWithDescription} from "../Card/CardHeading";
import {formatCurrency} from "../../../utilites/currency.ts";
import {IconArrowRight} from "@tabler/icons-react";
import classes from "./PlatformFeesSettings.module.scss";
import {AccountConfiguration} from "../../../types.ts";

const formatPercentage = (value: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'percent',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value / 100);
};

interface FeeBreakdownProps {
    ticketPrice: number;
    feePercentage: number;
    fixedFee: number;
    currency: string;
    passToBuyer: boolean;
}

const FeeBreakdown = ({ticketPrice, feePercentage, fixedFee, currency, passToBuyer}: FeeBreakdownProps) => {
    const percentageRate = feePercentage / 100;
    // Formula: P = (fixed + total * r) / (1 - r)
    // This ensures the platform fee exactly covers what Stripe will charge
    const platformFee = percentageRate >= 1
        ? fixedFee + (ticketPrice * percentageRate)
        : (fixedFee + (ticketPrice * percentageRate)) / (1 - percentageRate);
    const roundedPlatformFee = Math.round(platformFee * 100) / 100;

    const buyerPays = passToBuyer ? ticketPrice + roundedPlatformFee : ticketPrice;
    const organizerReceives = passToBuyer ? ticketPrice : ticketPrice - roundedPlatformFee;

    return (
        <div className={classes.breakdown}>
            <div className={classes.breakdownRow}>
                <span className={classes.breakdownLabel}>{t`Ticket price`}</span>
                <span className={classes.breakdownValue}>{formatCurrency(ticketPrice, currency)}</span>
            </div>
            {passToBuyer && (
                <div className={classes.breakdownRow}>
                    <span className={classes.breakdownLabel}>{t`Platform fee`}</span>
                    <span className={classes.breakdownValue}>+{formatCurrency(roundedPlatformFee, currency)}</span>
                </div>
            )}
            <div className={classes.breakdownDivider} />
            <div className={classes.breakdownRow}>
                <span className={classes.breakdownLabelBold}>{t`Buyer pays`}</span>
                <span className={classes.breakdownValueBold}>{formatCurrency(buyerPays, currency)}</span>
            </div>
            <div className={classes.breakdownRow}>
                <span className={classes.breakdownLabelBold}>{t`You receive`}</span>
                <span className={classes.breakdownValueBold}>{formatCurrency(organizerReceives, currency)}</span>
            </div>
            {!passToBuyer && roundedPlatformFee > 0 && (
                <div className={classes.breakdownNote}>
                    <Text size="xs" c="dimmed">
                        {t`Platform fee of ${formatCurrency(roundedPlatformFee, currency)} deducted from your payout`}
                    </Text>
                </div>
            )}
        </div>
    );
};

export interface PlatformFeesSettingsProps {
    configuration?: AccountConfiguration;
    currentValue: boolean;
    onSave: (passToBuyer: boolean) => void;
    isLoading: boolean;
    isSaving: boolean;
    heading: string;
    description: string;
    feeHandlingLabel: string;
    feeHandlingDescription: string;
}

export const PlatformFeesSettings = ({
    configuration,
    currentValue,
    onSave,
    isLoading,
    isSaving,
    heading,
    description,
    feeHandlingLabel,
    feeHandlingDescription,
}: PlatformFeesSettingsProps) => {
    const [samplePrice, setSamplePrice] = useState<number | string>(50);
    const [selectedOption, setSelectedOption] = useState<'pass' | 'absorb'>('absorb');
    const initializedRef = useRef(false);

    const feePercentage = configuration?.application_fees?.percentage || 0;
    const fixedFee = configuration?.application_fees?.fixed || 0;
    const feeCurrency = 'USD'; // Platform fees are always in USD

    const numericPrice = typeof samplePrice === 'number' ? samplePrice : parseFloat(samplePrice) || 0;

    const handleSave = () => {
        onSave(selectedOption === 'pass');
    };

    // Initialize selected option from currentValue only once after data loads
    useEffect(() => {
        if (!isLoading && !initializedRef.current) {
            setSelectedOption(currentValue ? 'pass' : 'absorb');
            initializedRef.current = true;
        }
    }, [isLoading, currentValue]);

    return (
        <Card>
            <HeadingWithDescription
                heading={heading}
                description={description}
            />

            <Stack gap="xl">
                {configuration && (
                    <Card variant="lightGray">
                        <Group justify="space-between" align="flex-start">
                            <div>
                                <Text size="sm" fw={500} mb="xs">{t`Your Plan`}</Text>
                                <Title order={4}>{configuration.name}</Title>
                            </div>
                            <div style={{textAlign: 'right'}}>
                                <Text size="sm" c="dimmed">{t`Platform fee`}</Text>
                                <Text fw={600}>
                                    {formatPercentage(feePercentage)}
                                    {fixedFee > 0 && ` + ${formatCurrency(fixedFee, feeCurrency)}`}
                                </Text>
                            </div>
                        </Group>
                    </Card>
                )}

                <div>
                    <Text fw={500} mb="sm">{feeHandlingLabel}</Text>
                    <Text size="sm" c="dimmed" mb="md">
                        {feeHandlingDescription}
                    </Text>

                    <NumberInput
                        label={t`Sample ticket price`}
                        value={samplePrice}
                        onChange={setSamplePrice}
                        min={0}
                        max={10000}
                        decimalScale={2}
                        prefix={formatCurrency(0, feeCurrency).replace(/[\d.,\s]/g, '')}
                        mb="lg"
                        style={{maxWidth: 200}}
                    />

                    <fieldset disabled={isLoading || isSaving}>
                        <SegmentedControl
                            value={selectedOption}
                            onChange={(value) => setSelectedOption(value as 'pass' | 'absorb')}
                            data={[
                                {label: t`Pass fee to buyer`, value: 'pass'},
                                {label: t`Absorb fee`, value: 'absorb'},
                            ]}
                            mb="md"
                            fullWidth
                            size="md"
                        />

                        <Grid mb="lg">
                            <Grid.Col span={{base: 12, sm: 6}}>
                                <Card
                                    variant={selectedOption === 'pass' ? 'default' : 'lightGray'}
                                    className={selectedOption === 'pass' ? classes.activeCard : ''}
                                >
                                    <Group gap="xs" mb="md">
                                        <Text fw={600}>{t`Pass to Buyer`}</Text>
                                        {selectedOption === 'pass' && (
                                            <Text size="xs" c="teal" fw={500}>{t`Selected`}</Text>
                                        )}
                                    </Group>
                                    <Text size="sm" c="dimmed" mb="md">
                                        {t`The platform fee is added to the ticket price. Buyers pay more, but you receive the full ticket price.`}
                                    </Text>
                                    <FeeBreakdown
                                        ticketPrice={numericPrice}
                                        feePercentage={feePercentage}
                                        fixedFee={fixedFee}
                                        currency={feeCurrency}
                                        passToBuyer={true}
                                    />
                                </Card>
                            </Grid.Col>
                            <Grid.Col span={{base: 12, sm: 6}}>
                                <Card
                                    variant={selectedOption === 'absorb' ? 'default' : 'lightGray'}
                                    className={selectedOption === 'absorb' ? classes.activeCard : ''}
                                >
                                    <Group gap="xs" mb="md">
                                        <Text fw={600}>{t`Absorb Fee`}</Text>
                                        {selectedOption === 'absorb' && (
                                            <Text size="xs" c="teal" fw={500}>{t`Selected`}</Text>
                                        )}
                                    </Group>
                                    <Text size="sm" c="dimmed" mb="md">
                                        {t`Buyers see a clean price. The platform fee is deducted from your payout.`}
                                    </Text>
                                    <FeeBreakdown
                                        ticketPrice={numericPrice}
                                        feePercentage={feePercentage}
                                        fixedFee={fixedFee}
                                        currency={feeCurrency}
                                        passToBuyer={false}
                                    />
                                </Card>
                            </Grid.Col>
                        </Grid>

                        <Button loading={isSaving} onClick={handleSave}>
                            {t`Save`}
                        </Button>
                    </fieldset>
                </div>

                <Card variant="lightGray">
                    <Group justify="space-between" align="center">
                        <div>
                            <Text fw={500} mb={4}>{t`Additional Fees`}</Text>
                            <Text size="sm" c="dimmed">
                                {t`You can configure additional service fees and taxes in your account settings.`}
                            </Text>
                        </div>
                        <Anchor href="/account/taxes-and-fees" size="sm">
                            <Group gap="xs">
                                <span>{t`Configure Taxes & Fees`}</span>
                                <IconArrowRight size={16} />
                            </Group>
                        </Anchor>
                    </Group>
                </Card>
            </Stack>
        </Card>
    );
};
