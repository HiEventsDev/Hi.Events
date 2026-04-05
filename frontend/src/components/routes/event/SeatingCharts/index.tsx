import {useCallback, useEffect, useRef, useState} from 'react';
import {useParams} from 'react-router';
import {
    ActionIcon,
    Badge,
    Button,
    ColorInput,
    Group,
    Modal,
    NumberInput,
    Select,
    Stack,
    Text,
    TextInput,
    Tooltip,
} from '@mantine/core';
import {useForm} from '@mantine/form';
import {useDisclosure} from '@mantine/hooks';
import {
    IconArmchair,
    IconGripVertical,
    IconLayout2,
    IconMinus,
    IconPlus,
    IconTrash,
    IconZoomIn,
    IconZoomOut,
    IconZoomReset,
} from '@tabler/icons-react';
import {t} from '@lingui/macro';
import {Card} from '../../../common/Card';
import {HeadingCard} from '../../../common/Card/CardHeading';
import {showError, showSuccess} from '../../../../utilites/notifications.tsx';
import {useGetSeatingCharts} from '../../../../queries/useGetSeatingCharts.ts';
import {useCreateSeatingChart} from '../../../../mutations/useCreateSeatingChart.ts';
import {SeatingChartSection} from '../../../../api/seating-chart.client.ts';
import classes from './SeatingCharts.module.scss';

interface CanvasSection extends Omit<SeatingChartSection, 'id' | 'seating_chart_id'> {
    tempId: string;
}

const SECTION_COLORS = [
    '#CD58DD', '#4C6EF5', '#40C057', '#FD7E14', '#FA5252',
    '#15AABF', '#BE4BDB', '#82C91E', '#E64980', '#7950F2',
];

const SeatingCharts = () => {
    const {eventId} = useParams();
    const chartsQuery = useGetSeatingCharts(eventId);
    const createMutation = useCreateSeatingChart();
    const [builderOpen, {open: openBuilder, close: closeBuilder}] = useDisclosure(false);
    const [sections, setSections] = useState<CanvasSection[]>([]);
    const [selectedSection, setSelectedSection] = useState<string | null>(null);
    const [zoom, setZoom] = useState(1);
    const [pan, setPan] = useState({x: 0, y: 0});
    const [isPanning, setIsPanning] = useState(false);
    const [panStart, setPanStart] = useState({x: 0, y: 0});
    const [dragSection, setDragSection] = useState<string | null>(null);
    const [dragOffset, setDragOffset] = useState({x: 0, y: 0});
    const canvasRef = useRef<HTMLDivElement>(null);

    const chartForm = useForm({
        initialValues: {
            name: '',
            description: '',
        },
        validate: {
            name: (v) => (!v ? t`Name is required` : null),
        },
    });

    const sectionForm = useForm({
        initialValues: {
            name: '',
            color: SECTION_COLORS[0],
            row_count: 5,
            seats_per_row: 10,
            shape: 'rectangle' as 'rectangle' | 'arc' | 'circle',
        },
        validate: {
            name: (v) => (!v ? t`Name is required` : null),
            row_count: (v) => (v < 1 ? t`Must be at least 1` : null),
            seats_per_row: (v) => (v < 1 ? t`Must be at least 1` : null),
        },
    });

    const [sectionModalOpen, {open: openSectionModal, close: closeSectionModal}] = useDisclosure(false);

    const totalSeats = sections.reduce((sum, s) => sum + s.row_count * s.seats_per_row, 0);

    const handleAddSection = () => {
        if (!sectionForm.validate().hasErrors) {
            const values = sectionForm.values;
            const newSection: CanvasSection = {
                tempId: crypto.randomUUID(),
                name: values.name,
                label: values.name,
                color: values.color,
                capacity: values.row_count * values.seats_per_row,
                row_count: values.row_count,
                seats_per_row: values.seats_per_row,
                position: {x: 50 + sections.length * 30, y: 50 + sections.length * 30},
                shape: values.shape,
            };
            setSections((prev) => [...prev, newSection]);
            setSelectedSection(newSection.tempId);
            closeSectionModal();
            sectionForm.reset();
            sectionForm.setFieldValue('color', SECTION_COLORS[(sections.length + 1) % SECTION_COLORS.length]);
        }
    };

    const handleRemoveSection = (tempId: string) => {
        setSections((prev) => prev.filter((s) => s.tempId !== tempId));
        if (selectedSection === tempId) setSelectedSection(null);
    };

    const handleSaveChart = () => {
        if (!chartForm.validate().hasErrors && sections.length > 0) {
            createMutation.mutate(
                {
                    eventId: eventId!,
                    data: {
                        name: chartForm.values.name,
                        description: chartForm.values.description || null,
                        total_seats: totalSeats,
                        is_active: true,
                        sections: sections.map((s, i) => ({
                            name: s.name,
                            label: s.label,
                            color: s.color,
                            capacity: s.capacity,
                            row_count: s.row_count,
                            seats_per_row: s.seats_per_row,
                            position: s.position,
                            shape: s.shape,
                            sort_order: i,
                        })),
                    },
                },
                {
                    onSuccess: () => {
                        showSuccess(t`Seating chart created successfully`);
                        closeBuilder();
                        setSections([]);
                        chartForm.reset();
                    },
                    onError: () => {
                        showError(t`Failed to create seating chart`);
                    },
                }
            );
        } else if (sections.length === 0) {
            showError(t`Add at least one section`);
        }
    };

    // Canvas interactions
    const handleCanvasMouseDown = useCallback(
        (e: React.MouseEvent) => {
            if (e.target === canvasRef.current || (e.target as HTMLElement).classList.contains(classes.gridPattern)) {
                setIsPanning(true);
                setPanStart({x: e.clientX - pan.x, y: e.clientY - pan.y});
                setSelectedSection(null);
            }
        },
        [pan]
    );

    const handleCanvasMouseMove = useCallback(
        (e: React.MouseEvent) => {
            if (isPanning) {
                setPan({x: e.clientX - panStart.x, y: e.clientY - panStart.y});
            }
            if (dragSection) {
                const rect = canvasRef.current?.getBoundingClientRect();
                if (rect) {
                    const x = (e.clientX - rect.left - pan.x) / zoom - dragOffset.x;
                    const y = (e.clientY - rect.top - pan.y) / zoom - dragOffset.y;
                    setSections((prev) =>
                        prev.map((s) => (s.tempId === dragSection ? {...s, position: {x: Math.max(0, x), y: Math.max(0, y)}} : s))
                    );
                }
            }
        },
        [isPanning, panStart, dragSection, dragOffset, pan, zoom]
    );

    const handleCanvasMouseUp = useCallback(() => {
        setIsPanning(false);
        setDragSection(null);
    }, []);

    const handleSectionMouseDown = useCallback(
        (e: React.MouseEvent, tempId: string, section: CanvasSection) => {
            e.stopPropagation();
            setSelectedSection(tempId);
            setDragSection(tempId);
            const rect = canvasRef.current?.getBoundingClientRect();
            if (rect) {
                const mouseX = (e.clientX - rect.left - pan.x) / zoom;
                const mouseY = (e.clientY - rect.top - pan.y) / zoom;
                setDragOffset({x: mouseX - section.position.x, y: mouseY - section.position.y});
            }
        },
        [pan, zoom]
    );

    useEffect(() => {
        const handleMouseUp = () => {
            setIsPanning(false);
            setDragSection(null);
        };
        window.addEventListener('mouseup', handleMouseUp);
        return () => window.removeEventListener('mouseup', handleMouseUp);
    }, []);

    const handleZoomIn = () => setZoom((z) => Math.min(z + 0.15, 2.5));
    const handleZoomOut = () => setZoom((z) => Math.max(z - 0.15, 0.3));
    const handleZoomReset = () => {
        setZoom(1);
        setPan({x: 0, y: 0});
    };

    const renderSectionSeats = (section: CanvasSection) => {
        const rows = [];
        for (let r = 0; r < Math.min(section.row_count, 12); r++) {
            const seats = [];
            const rowLabel = String.fromCharCode(65 + r);
            for (let s = 0; s < Math.min(section.seats_per_row, 20); s++) {
                seats.push(
                    <Tooltip key={s} label={`${rowLabel}${s + 1}`} withArrow position="top">
                        <div
                            className={`${classes.seat} ${classes.seatAvailable}`}
                            style={{backgroundColor: section.color}}
                        >
                            {section.seats_per_row <= 12 ? s + 1 : ''}
                        </div>
                    </Tooltip>
                );
            }
            if (section.seats_per_row > 20) {
                seats.push(
                    <Text key="more" size="xs" c="dimmed">
                        +{section.seats_per_row - 20}
                    </Text>
                );
            }
            rows.push(
                <div key={r} className={classes.seatRow}>
                    <span className={classes.rowLabel}>{rowLabel}</span>
                    {seats}
                </div>
            );
        }
        if (section.row_count > 12) {
            rows.push(
                <Text key="moreRows" size="xs" c="dimmed" ta="center">
                    +{section.row_count - 12} {t`more rows`}
                </Text>
            );
        }
        return rows;
    };

    // Chart list view
    const charts = chartsQuery.data?.data;

    if (builderOpen) {
        return (
            <div className={classes.builder}>
                <Group justify="space-between">
                    <Group gap="sm">
                        <TextInput
                            placeholder={t`Chart Name`}
                            {...chartForm.getInputProps('name')}
                            style={{width: 250}}
                        />
                        <TextInput
                            placeholder={t`Description (optional)`}
                            {...chartForm.getInputProps('description')}
                            style={{width: 300}}
                        />
                    </Group>
                    <Group gap="sm">
                        <Button variant="default" onClick={closeBuilder}>{t`Cancel`}</Button>
                        <Button
                            onClick={handleSaveChart}
                            loading={createMutation.isPending}
                            disabled={sections.length === 0}
                        >
                            {t`Save Chart`}
                        </Button>
                    </Group>
                </Group>

                <div className={classes.toolbar}>
                    <Button
                        size="xs"
                        leftSection={<IconPlus size={14}/>}
                        onClick={openSectionModal}
                    >
                        {t`Add Section`}
                    </Button>
                    <Badge variant="light" size="lg">
                        {t`${totalSeats} Total Seats`}
                    </Badge>
                    <Badge variant="light" size="lg" color="blue">
                        {t`${sections.length} Sections`}
                    </Badge>
                    <div className={classes.zoomControls}>
                        <ActionIcon variant="default" size="sm" onClick={handleZoomOut}>
                            <IconZoomOut size={14}/>
                        </ActionIcon>
                        <Text size="xs" fw={600} style={{minWidth: 45, textAlign: 'center'}}>
                            {Math.round(zoom * 100)}%
                        </Text>
                        <ActionIcon variant="default" size="sm" onClick={handleZoomIn}>
                            <IconZoomIn size={14}/>
                        </ActionIcon>
                        <ActionIcon variant="default" size="sm" onClick={handleZoomReset} ml={4}>
                            <IconZoomReset size={14}/>
                        </ActionIcon>
                    </div>
                </div>

                <div className={classes.canvasArea}>
                    <div className={classes.sidebar}>
                        <div className={classes.sidebarSection}>
                            <div className={classes.sidebarSectionTitle}>{t`Sections`}</div>
                            {sections.length === 0 ? (
                                <Text size="sm" c="dimmed">{t`No sections added yet`}</Text>
                            ) : (
                                <div className={classes.sectionList}>
                                    {sections.map((section) => (
                                        <div
                                            key={section.tempId}
                                            className={`${classes.sectionListItem} ${selectedSection === section.tempId ? classes.sectionListItemActive : ''}`}
                                            onClick={() => setSelectedSection(section.tempId)}
                                        >
                                            <div
                                                className={classes.sectionColorDot}
                                                style={{backgroundColor: section.color}}
                                            />
                                            <div className={classes.sectionListInfo}>
                                                <div className={classes.sectionListName}>{section.name}</div>
                                                <div className={classes.sectionListMeta}>
                                                    {section.row_count} × {section.seats_per_row} = {section.row_count * section.seats_per_row} {t`seats`}
                                                </div>
                                            </div>
                                            <ActionIcon
                                                variant="subtle"
                                                color="red"
                                                size="sm"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    handleRemoveSection(section.tempId);
                                                }}
                                            >
                                                <IconTrash size={14}/>
                                            </ActionIcon>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {selectedSection && (() => {
                            const section = sections.find((s) => s.tempId === selectedSection);
                            if (!section) return null;
                            return (
                                <div className={classes.sidebarSection}>
                                    <div className={classes.sidebarSectionTitle}>{t`Section Properties`}</div>
                                    <Stack gap="xs">
                                        <TextInput
                                            size="xs"
                                            label={t`Name`}
                                            value={section.name}
                                            onChange={(e) =>
                                                setSections((prev) =>
                                                    prev.map((s) =>
                                                        s.tempId === selectedSection ? {...s, name: e.target.value, label: e.target.value} : s
                                                    )
                                                )
                                            }
                                        />
                                        <ColorInput
                                            size="xs"
                                            label={t`Color`}
                                            value={section.color}
                                            swatches={SECTION_COLORS}
                                            onChange={(color) =>
                                                setSections((prev) =>
                                                    prev.map((s) =>
                                                        s.tempId === selectedSection ? {...s, color} : s
                                                    )
                                                )
                                            }
                                        />
                                        <Group grow>
                                            <NumberInput
                                                size="xs"
                                                label={t`Rows`}
                                                min={1}
                                                max={50}
                                                value={section.row_count}
                                                onChange={(v) =>
                                                    setSections((prev) =>
                                                        prev.map((s) =>
                                                            s.tempId === selectedSection
                                                                ? {...s, row_count: Number(v) || 1, capacity: (Number(v) || 1) * s.seats_per_row}
                                                                : s
                                                        )
                                                    )
                                                }
                                            />
                                            <NumberInput
                                                size="xs"
                                                label={t`Seats/Row`}
                                                min={1}
                                                max={100}
                                                value={section.seats_per_row}
                                                onChange={(v) =>
                                                    setSections((prev) =>
                                                        prev.map((s) =>
                                                            s.tempId === selectedSection
                                                                ? {...s, seats_per_row: Number(v) || 1, capacity: s.row_count * (Number(v) || 1)}
                                                                : s
                                                        )
                                                    )
                                                }
                                            />
                                        </Group>
                                        <Select
                                            size="xs"
                                            label={t`Shape`}
                                            data={[
                                                {value: 'rectangle', label: t`Rectangle`},
                                                {value: 'arc', label: t`Arc / Curved`},
                                                {value: 'circle', label: t`Circle / Round`},
                                            ]}
                                            value={section.shape}
                                            onChange={(v) =>
                                                setSections((prev) =>
                                                    prev.map((s) =>
                                                        s.tempId === selectedSection ? {...s, shape: (v as 'rectangle' | 'arc' | 'circle') || 'rectangle'} : s
                                                    )
                                                )
                                            }
                                        />
                                    </Stack>
                                </div>
                            );
                        })()}
                    </div>

                    <div
                        ref={canvasRef}
                        className={classes.canvas}
                        onMouseDown={handleCanvasMouseDown}
                        onMouseMove={handleCanvasMouseMove}
                        onMouseUp={handleCanvasMouseUp}
                    >
                        <div className={classes.gridPattern}/>
                        <div
                            className={classes.canvasInner}
                            style={{
                                transform: `translate(${pan.x}px, ${pan.y}px) scale(${zoom})`,
                            }}
                        >
                            {sections.map((section) => (
                                <div
                                    key={section.tempId}
                                    className={`${classes.section} ${selectedSection === section.tempId ? classes.sectionSelected : ''}`}
                                    style={{
                                        left: section.position.x,
                                        top: section.position.y,
                                        borderColor: section.color,
                                    }}
                                    onMouseDown={(e) => handleSectionMouseDown(e, section.tempId, section)}
                                >
                                    <div className={classes.sectionHeader} style={{backgroundColor: section.color}}>
                                        <span>
                                            <IconGripVertical size={12} style={{verticalAlign: 'middle', marginRight: 4}}/>
                                            {section.name}
                                        </span>
                                        <Badge size="xs" variant="white" color="dark">
                                            {section.row_count * section.seats_per_row}
                                        </Badge>
                                    </div>
                                    <div className={classes.sectionBody}>
                                        {renderSectionSeats(section)}
                                    </div>
                                </div>
                            ))}
                        </div>

                        {sections.length === 0 && (
                            <div className={classes.emptyCanvas}>
                                <IconLayout2 size={64} className={classes.emptyCanvasIcon}/>
                                <Text size="lg" fw={600}>{t`Design Your Seating Layout`}</Text>
                                <Text size="sm">{t`Click "Add Section" to start building your seating chart`}</Text>
                            </div>
                        )}
                    </div>
                </div>

                {/* Add Section Modal */}
                <Modal
                    opened={sectionModalOpen}
                    onClose={closeSectionModal}
                    title={t`Add Section`}
                    centered
                >
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            handleAddSection();
                        }}
                    >
                        <Stack>
                            <TextInput
                                label={t`Section Name`}
                                placeholder={t`e.g., Orchestra, Balcony, VIP`}
                                {...sectionForm.getInputProps('name')}
                            />
                            <ColorInput
                                label={t`Section Color`}
                                swatches={SECTION_COLORS}
                                {...sectionForm.getInputProps('color')}
                            />
                            <Group grow>
                                <NumberInput
                                    label={t`Number of Rows`}
                                    min={1}
                                    max={50}
                                    {...sectionForm.getInputProps('row_count')}
                                />
                                <NumberInput
                                    label={t`Seats Per Row`}
                                    min={1}
                                    max={100}
                                    {...sectionForm.getInputProps('seats_per_row')}
                                />
                            </Group>
                            <Select
                                label={t`Section Shape`}
                                data={[
                                    {value: 'rectangle', label: t`Rectangle`},
                                    {value: 'arc', label: t`Arc / Curved`},
                                    {value: 'circle', label: t`Circle / Round`},
                                ]}
                                {...sectionForm.getInputProps('shape')}
                            />
                            <Text size="sm" c="dimmed">
                                {t`This section will have ${sectionForm.values.row_count * sectionForm.values.seats_per_row} seats`}
                            </Text>
                            <Button type="submit">{t`Add Section`}</Button>
                        </Stack>
                    </form>
                </Modal>
            </div>
        );
    }

    // List view
    return (
        <Card>
            <HeadingCard
                heading={t`Seating Charts`}
                descriptionText={t`Design visual seating layouts for your venue. Create sections, arrange seats, and manage seat assignments.`}
            >
                <Button leftSection={<IconPlus size={16}/>} onClick={openBuilder}>
                    {t`Create Seating Chart`}
                </Button>
            </HeadingCard>

            {charts && charts.length > 0 ? (
                <div className={classes.chartList}>
                    {(Array.isArray(charts) ? charts : []).map((chart) => (
                        <div key={chart.id} className={classes.chartCard}>
                            <div className={classes.chartCardHeader}>
                                <div className={classes.chartCardTitle}>{chart.name}</div>
                                <Badge variant={chart.is_active ? 'filled' : 'light'} color={chart.is_active ? 'green' : 'gray'}>
                                    {chart.is_active ? t`Active` : t`Inactive`}
                                </Badge>
                            </div>
                            {chart.description && (
                                <div className={classes.chartCardMeta}>{chart.description}</div>
                            )}
                            <Group gap="lg" mt="sm">
                                <Group gap={4}>
                                    <IconArmchair size={16}/>
                                    <Text size="sm" fw={600}>{chart.total_seats} {t`seats`}</Text>
                                </Group>
                            </Group>
                        </div>
                    ))}
                </div>
            ) : (
                <div style={{textAlign: 'center', padding: '60px 20px'}}>
                    <IconLayout2 size={48} style={{opacity: 0.3, marginBottom: 12}}/>
                    <Text size="lg" fw={600} mb={4}>{t`No Seating Charts Yet`}</Text>
                    <Text size="sm" c="dimmed" mb="lg">
                        {t`Create a seating chart to enable reserved seating for your event.`}
                    </Text>
                    <Button leftSection={<IconPlus size={16}/>} onClick={openBuilder}>
                        {t`Create Your First Seating Chart`}
                    </Button>
                </div>
            )}
        </Card>
    );
};

export default SeatingCharts;
