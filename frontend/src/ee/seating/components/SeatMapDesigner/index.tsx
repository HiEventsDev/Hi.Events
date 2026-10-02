import {PointerEvent as ReactPointerEvent, useCallback, useEffect, useMemo, useRef, useState} from "react";
import {ActionIcon, Button, Menu, TextInput, Tooltip} from "@mantine/core";
import {useWindowEvent} from "@mantine/hooks";
import {
    IconAbc,
    IconArmchair,
    IconArrowBackUp,
    IconArrowForwardUp,
    IconArrowLeft,
    IconChevronDown,
    IconClick,
    IconGridDots,
    IconHandStop,
    IconMaximize,
    IconMinus,
    IconPlus,
    IconPolygon,
    IconRectangle,
    IconRosette,
} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {useBlocker, useNavigate} from "react-router";
import {confirmationDialogAsync} from "../../../../utilites/confirmationDialog.tsx";
import {showError} from "../../../../utilites/notifications.tsx";
import {countSeats, rowLabel} from "../lib/generateSeats.ts";
import {areaBounds} from "../lib/geometry.ts";
import {isSeatedElement, SeatMapLayout, SeatOverride} from "../lib/types.ts";
import {Canvas, HoverHandle} from "./Canvas.tsx";
import {ElementInspector, SeatInspector} from "./Inspector.tsx";
import {BandsPanel} from "./BandsPanel.tsx";
import {addArea, duplicateArea, moveElementsToArea, removeArea, renameArea, withStageFocalPoints} from "./ops/areas.ts";
import {
    alignElements,
    duplicateElements,
    moveElements,
    patchElements,
    removeElements,
    restoreRemovedSeats,
    setRowLabel,
    setSeatOverrides,
} from "./ops/elements.ts";
import {addBand, removeBand, bandUsage, updateBand} from "./ops/bands.ts";
import {findLayoutProblem} from "./problems.ts";
import {closePolygon, DRAWING_TOOLS, EMPTY_SELECTION, Gesture, GRID, PointerInfo, Selection, Tool, toolForPointerDown, ToolContext, ToolName, TOOLS} from "./tools.ts";
import {useCanvasView} from "./useCanvasView.ts";
import {useUndoableLayout} from "./useUndoableLayout.ts";
import classes from "./SeatMapDesigner.module.scss";

interface SeatMapDesignerProps {
    initialLayout: SeatMapLayout;
    initialName: string;
    isNameEditable: boolean;
    backTo: string;
    isSaving: boolean;
    onSave: (layout: SeatMapLayout, name: string) => Promise<boolean>;
}

const TOOLBAR: {tool: ToolName; shortcut: string; Icon: typeof IconClick; label: () => string; hint?: () => string}[] = [
    {tool: 'select', shortcut: 'v', Icon: IconClick, label: () => t`Select and move`},
    {tool: 'seats', shortcut: 's', Icon: IconArmchair, label: () => t`Edit individual seats`,
        hint: () => t`Click seats or drag a box around them, then change their band, mark them accessible or remove them.`},
    {tool: 'pan', shortcut: 'h', Icon: IconHandStop, label: () => t`Pan`},
    {tool: 'rows', shortcut: 'r', Icon: IconGridDots, label: () => t`Draw rows of seats`,
        hint: () => t`Press where the first seat goes and drag. Drag sideways for seats, down for more rows.`},
    {tool: 'table', shortcut: 't', Icon: IconRosette, label: () => t`Place tables`,
        hint: () => t`Click to place a table. Change its chairs and shape on the right.`},
    {tool: 'zone', shortcut: 'g', Icon: IconPolygon, label: () => t`Draw a standing zone`,
        hint: () => t`Click to add corners. Click the first corner or press Enter to finish.`},
    {tool: 'object', shortcut: 'o', Icon: IconRectangle, label: () => t`Draw a stage or other object`,
        hint: () => t`Drag to draw a stage. Change it to a bar, dance floor, entrance, pillar or wall on the right.`},
    {tool: 'label', shortcut: 'x', Icon: IconAbc, label: () => t`Add a text label`,
        hint: () => t`Click to place a label, then edit its text on the right.`},
];

const NUDGES: Record<string, [number, number]> = {
    ArrowLeft: [-1, 0],
    ArrowRight: [1, 0],
    ArrowUp: [0, -1],
    ArrowDown: [0, 1],
};

const ZOOM_STEP = 1.2;
const PINCH_ZOOM_SENSITIVITY = 0.002;

const isTyping = (target: EventTarget | null) =>
    target instanceof HTMLElement && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);

export const SeatMapDesigner = ({initialLayout, initialName, isNameEditable, backTo, isSaving, onSave}: SeatMapDesignerProps) => {
    const navigate = useNavigate();
    const svgRef = useRef<SVGSVGElement>(null);
    const hoverRef = useRef<HoverHandle>(null);
    const editBefore = useRef<SeatMapLayout | null>(null);
    const gestureTool = useRef<Tool>(TOOLS.select);
    const {layout, isDirty, canUndo, canRedo, preview, commit, apply, undo, redo, markSaved} = useUndoableLayout(initialLayout);
    const {view, toLayoutPoint, zoomAt, panBy, fit} = useCanvasView(svgRef);

    const [name, setName] = useState(initialName);
    const [savedName, setSavedName] = useState(initialName);
    const [areaId, setAreaId] = useState(initialLayout.areas[0].id);
    const [tool, setTool] = useState<ToolName>('select');
    const [selection, setSelection] = useState<Selection>(EMPTY_SELECTION);
    const [gesture, setGesture] = useState<Gesture | null>(null);
    const [activeBand, setActiveBand] = useState(initialLayout.bands[0].key);
    const [isSpaceHeld, setIsSpaceHeld] = useState(false);

    const area = layout.areas.find(candidate => candidate.id === areaId) ?? layout.areas[0];
    const band = layout.bands.some(candidate => candidate.key === activeBand) ? activeBand : layout.bands[0].key;
    const bands = useMemo(() => new Map(layout.bands.map(candidate => [candidate.key, candidate])), [layout.bands]);
    const usage = useMemo(() => bandUsage(layout), [layout]);
    const seatCount = useMemo(() => countSeats(layout), [layout]);
    const selectedElementIds = useMemo(() => new Set(selection.elements), [selection.elements]);
    const selectedSeatUids = useMemo(() => new Set(selection.seats), [selection.seats]);
    const selectedElements = useMemo(
        () => area.elements.filter(element => selectedElementIds.has(element.id)),
        [area.elements, selectedElementIds],
    );
    const selectedSeats = useMemo(
        () => area.elements.filter(isSeatedElement).flatMap(element => element.seats).filter(seat => selectedSeatUids.has(seat.uid)),
        [area.elements, selectedSeatUids],
    );
    const selectedRow = useMemo(() => {
        const elementIds = new Set(selectedSeats.map(seat => seat.uid.split('.')[0]));
        const rows = new Set(selectedSeats.map(seat => seat.row));
        const element = elementIds.size === 1 && rows.size === 1
            ? area.elements.find(candidate => elementIds.has(candidate.id))
            : undefined;
        if (!element || (element.type !== 'row' && element.type !== 'block')) {
            return null;
        }
        const row = selectedSeats[0].row;
        return {
            elementId: element.id,
            row,
            label: element.rowLabels?.[row] ?? '',
            automaticLabel: rowLabel({...element, rowLabels: undefined}, row),
        };
    }, [selectedSeats, area.elements]);
    const hasUnsavedChanges = isDirty || name !== savedName;

    const fitArea = useCallback(() => fit(areaBounds(area)), [fit, area]);

    useEffect(() => {
        fitArea();
    }, [area.id]);

    const changeTool = (next: ToolName) => {
        setTool(next);
        hoverRef.current?.setPoint(null);
        setGesture(null);
        setSelection(current => (next === 'seats' ? {elements: [], seats: current.seats} : {elements: current.elements, seats: []}));
    };

    const changeArea = (nextAreaId: string) => {
        setAreaId(nextAreaId);
        setSelection(EMPTY_SELECTION);
        setGesture(null);
    };

    const context: ToolContext = {
        layout,
        area,
        selection,
        band,
        scale: view.scale,
        preview,
        commit,
        apply,
        select: setSelection,
        panBy,
    };

    const previewEdit = (change: (current: SeatMapLayout) => SeatMapLayout) => {
        editBefore.current ??= layout;
        preview(change(layout));
    };

    const commitEdit = () => {
        if (editBefore.current) {
            commit(editBefore.current);
            editBefore.current = null;
        }
    };

    const hint = TOOLBAR.find(entry => entry.tool === tool)?.hint;

    const handleWheel = useCallback((event: WheelEvent) => {
        if (event.ctrlKey || event.metaKey) {
            zoomAt(Math.exp(-event.deltaY * PINCH_ZOOM_SENSITIVITY), event.clientX, event.clientY);
        } else {
            panBy(-event.deltaX, -event.deltaY);
        }
    }, [zoomAt, panBy]);

    const toPointerInfo = (event: ReactPointerEvent): PointerInfo => ({
        point: toLayoutPoint(event.clientX, event.clientY),
        client: {x: event.clientX, y: event.clientY},
        shiftKey: event.shiftKey,
        seatUid: (event.target as Element).closest('[data-uid]')?.getAttribute('data-uid') ?? null,
    });

    const deleteSelection = () => {
        apply(current => removeElements(current, area.id, selection.elements));
        setSelection(EMPTY_SELECTION);
    };

    const duplicateSelection = () => {
        const result = duplicateElements(layout, area.id, selection.elements);
        apply(() => result.layout);
        setSelection({elements: result.ids, seats: []});
    };

    useWindowEvent('keydown', event => {
        if (isTyping(event.target)) {
            return;
        }
        const key = event.key.toLowerCase();
        const isCommand = event.metaKey || event.ctrlKey;

        if (isCommand && key === 'z') {
            event.preventDefault();
            setSelection(EMPTY_SELECTION);
            (event.shiftKey ? redo : undo)();
        } else if (isCommand && key === 'd' && selection.elements.length > 0) {
            event.preventDefault();
            duplicateSelection();
        } else if ((key === 'delete' || key === 'backspace') && selection.elements.length > 0) {
            event.preventDefault();
            deleteSelection();
        } else if ((key === 'delete' || key === 'backspace') && selection.seats.length > 0) {
            event.preventDefault();
            apply(current => setSeatOverrides(current, area.id, selection.seats, {removed: true}));
            setSelection(EMPTY_SELECTION);
        } else if (key === 'escape') {
            if (gesture && 'before' in gesture) {
                preview(gesture.before);
            }
            setGesture(null);
            setSelection(EMPTY_SELECTION);
            setTool('select');
        } else if (key === 'enter' && gesture?.kind === 'polygon') {
            closePolygon(context, gesture.pts);
            setGesture(null);
        } else if (key === ' ') {
            event.preventDefault();
            setIsSpaceHeld(true);
        } else if (NUDGES[event.key] && selection.elements.length > 0) {
            event.preventDefault();
            const distance = event.shiftKey ? GRID : 1;
            apply(current => moveElements(current, area.id, selection.elements, NUDGES[event.key][0] * distance, NUDGES[event.key][1] * distance));
        } else if (!isCommand) {
            const shortcut = TOOLBAR.find(entry => entry.shortcut === key);
            if (shortcut) {
                changeTool(shortcut.tool);
            }
        }
    });

    useWindowEvent('keyup', event => {
        if (event.key === ' ') {
            setIsSpaceHeld(false);
        }
    });

    useWindowEvent('beforeunload', event => {
        if (hasUnsavedChanges) {
            event.preventDefault();
        }
    });

    const save = async () => {
        commitEdit();
        const problem = findLayoutProblem(layout);
        if (problem) {
            changeArea(problem.areaId);
            setTool('select');
            setSelection({elements: problem.elementIds, seats: []});
            showError(problem.message);
            return;
        }
        if (await onSave(withStageFocalPoints(layout), name.trim())) {
            markSaved(layout);
            setSavedName(name);
        }
    };

    const blocker = useBlocker(({currentLocation, nextLocation}) =>
        hasUnsavedChanges && currentLocation.pathname !== nextLocation.pathname);

    useEffect(() => {
        if (blocker.state !== 'blocked') {
            return;
        }
        confirmationDialogAsync(t`Leave without saving? Your changes to this seat map will be lost.`, {confirm: t`Leave`})
            .then(confirmed => confirmed ? blocker.proceed() : blocker.reset());
    }, [blocker.state]);

    const leave = () => navigate(backTo);

    return (
        <div className={classes.designer}>
            <header className={classes.topBar}>
                <Tooltip label={t`Back`}>
                    <ActionIcon variant="subtle" color="gray" aria-label={t`Back`} onClick={leave}>
                        <IconArrowLeft size={18}/>
                    </ActionIcon>
                </Tooltip>
                {isNameEditable
                    ? <TextInput className={classes.nameInput} aria-label={t`Seat map name`} maxLength={100} value={name}
                                 onChange={event => setName(event.currentTarget.value)}/>
                    : <h1 className={classes.title}>{name}</h1>}
                <span className={classes.seatCount} data-testid="seat-map-designer-seat-count">{t`${seatCount} seats`}</span>

                <div className={classes.topBarActions}>
                    <Tooltip label={t`Undo`}>
                        <ActionIcon variant="default" aria-label={t`Undo`} disabled={!canUndo} onClick={undo}
                                    data-testid="seat-map-designer-undo-button">
                            <IconArrowBackUp size={16}/>
                        </ActionIcon>
                    </Tooltip>
                    <Tooltip label={t`Redo`}>
                        <ActionIcon variant="default" aria-label={t`Redo`} disabled={!canRedo} onClick={redo}>
                            <IconArrowForwardUp size={16}/>
                        </ActionIcon>
                    </Tooltip>
                    <Button size="sm" onClick={save} loading={isSaving} disabled={!hasUnsavedChanges || name.trim() === ''}
                            data-testid="seat-map-designer-save-button">
                        {t`Save`}
                    </Button>
                </div>
            </header>

            <nav className={classes.areaTabs} aria-label={t`Areas`}>
                {layout.areas.map(candidate => (
                    <button key={candidate.id} type="button" className={classes.areaTab} data-active={candidate.id === area.id}
                            onClick={() => changeArea(candidate.id)}>
                        {candidate.name}
                    </button>
                ))}
                <Menu position="bottom-start">
                    <Menu.Target>
                        <ActionIcon variant="subtle" color="gray" aria-label={t`Area options`}
                                    data-testid="seat-map-designer-area-menu-button">
                            <IconChevronDown size={16}/>
                        </ActionIcon>
                    </Menu.Target>
                    <Menu.Dropdown>
                        <Menu.Item data-testid="seat-map-designer-add-area-menu-item" onClick={() => {
                            const result = addArea(layout, t`New area`);
                            apply(() => result.layout);
                            changeArea(result.areaId);
                        }}>
                            {t`Add an area`}
                        </Menu.Item>
                        <Menu.Item onClick={() => {
                            const result = duplicateArea(layout, area.id);
                            apply(() => result.layout);
                            changeArea(result.areaId);
                        }}>
                            {t`Duplicate ${area.name}`}
                        </Menu.Item>
                        <Menu.Item color="red" disabled={layout.areas.length === 1} onClick={() => {
                            apply(current => removeArea(current, area.id));
                            changeArea(layout.areas.find(candidate => candidate.id !== area.id)?.id ?? area.id);
                        }}>
                            {t`Delete ${area.name}`}
                        </Menu.Item>
                    </Menu.Dropdown>
                </Menu>
            </nav>

            <div className={classes.workspace}>
                <aside className={classes.toolbar} aria-label={t`Tools`}>
                    {TOOLBAR.map(({tool: entry, shortcut, Icon, label}) => (
                        <Tooltip key={entry} label={`${label()} (${shortcut.toUpperCase()})`} position="right">
                            <ActionIcon size="lg" variant={tool === entry ? 'filled' : 'subtle'} color={tool === entry ? undefined : 'gray'}
                                        aria-label={label()} aria-pressed={tool === entry} onClick={() => changeTool(entry)}
                                        data-testid={`seat-map-designer-tool-${entry}`}>
                                <Icon size={20}/>
                            </ActionIcon>
                        </Tooltip>
                    ))}
                </aside>

                <div className={classes.canvasWrap}>
                    <Canvas svgRef={svgRef} hoverRef={hoverRef} area={area} bands={bands} view={view}
                            tool={isSpaceHeld ? 'pan' : tool} context={context}
                            selectedElements={selectedElements} selectedSeatUids={selectedSeatUids}
                            gesture={gesture} isHoverTracked={gesture === null && !isSpaceHeld}
                            toPointerInfo={toPointerInfo}
                            onPointerDown={(pointer, event) => {
                                gestureTool.current = isSpaceHeld || event.button === 1
                                    ? TOOLS.pan
                                    : toolForPointerDown(tool, context, pointer.point, gesture);
                                setGesture(gestureTool.current.down(context, pointer, gesture));
                            }}
                            onPointerMove={pointer => {
                                if (DRAWING_TOOLS.includes(tool)) {
                                    hoverRef.current?.setPoint(pointer.point);
                                }
                                if (gesture) {
                                    setGesture(gestureTool.current.move?.(context, pointer, gesture) ?? gesture);
                                }
                            }}
                            onPointerUp={() => gesture && setGesture(gestureTool.current.up?.(context, gesture) ?? null)}
                            onPointerLeave={() => hoverRef.current?.setPoint(null)}
                            onWheel={handleWheel}/>
                    <div className={classes.zoomControls}>
                        <ActionIcon variant="default" aria-label={t`Zoom out`} onClick={() => zoomAt(1 / ZOOM_STEP)}>
                            <IconMinus size={16}/>
                        </ActionIcon>
                        <ActionIcon variant="default" aria-label={t`Zoom in`} onClick={() => zoomAt(ZOOM_STEP)}>
                            <IconPlus size={16}/>
                        </ActionIcon>
                        <ActionIcon variant="default" aria-label={t`Fit to screen`} onClick={fitArea}>
                            <IconMaximize size={16}/>
                        </ActionIcon>
                    </div>
                    {hint && <div className={classes.canvasHint}>{hint()}</div>}
                </div>

                <aside className={classes.sidePanel}>
                    {selectedElements.length > 0 && (
                        <ElementInspector
                            elements={selectedElements} bands={layout.bands} areas={layout.areas} areaId={area.id}
                            onPreview={patch => previewEdit(current => patchElements(current, area.id, selection.elements, patch))}
                            onApply={patch => apply(current => patchElements(current, area.id, selection.elements, patch))}
                            onCommit={commitEdit}
                            onAlign={edge => apply(current => alignElements(current, area.id, selection.elements, edge))}
                            onDuplicate={duplicateSelection}
                            onDelete={deleteSelection}
                            onMoveToArea={target => {
                                apply(current => moveElementsToArea(current, area.id, target, selection.elements));
                                setSelection(EMPTY_SELECTION);
                            }}
                            onRestoreSeats={() => apply(current => restoreRemovedSeats(current, area.id, selection.elements[0]))}/>
                    )}

                    {selectedSeats.length > 0 && (
                        <SeatInspector
                            seats={selectedSeats} bands={layout.bands} row={selectedRow}
                            onRowLabelChange={label => selectedRow && previewEdit(
                                current => setRowLabel(current, area.id, selectedRow.elementId, selectedRow.row, label),
                            )}
                            onPreview={patch => previewEdit(current => setSeatOverrides(current, area.id, selection.seats, patch))}
                            onApply={(patch: SeatOverride) => {
                                apply(current => setSeatOverrides(current, area.id, selection.seats, patch));
                                if (patch.removed) {
                                    setSelection(EMPTY_SELECTION);
                                }
                            }}
                            onCommit={commitEdit}/>
                    )}

                    {selectedElements.length === 0 && selectedSeats.length === 0 && (
                        <section className={classes.panelSection} onBlur={commitEdit}>
                            <h3 className={classes.panelHeading}>{t`Area`}</h3>
                            <TextInput size="xs" label={t`Area name`} maxLength={50} value={area.name}
                                       error={area.name.trim() === ''}
                                       onChange={event => {
                                           const value = event.currentTarget.value;
                                           previewEdit(current => renameArea(current, area.id, value));
                                       }}/>
                            <p className={classes.panelHint}>
                                {t`Use areas for separate parts of the venue, such as stalls and balcony. Best available seats are chosen nearest the stage.`}
                            </p>
                        </section>
                    )}

                    <BandsPanel
                        bands={layout.bands} usage={usage} activeBand={band} onSelect={setActiveBand}
                        onPreview={(key, patch) => previewEdit(current => updateBand(current, key, patch))}
                        onCommit={commitEdit}
                        onAdd={() => apply(current => addBand(current, t`New band`))}
                        onRemove={key => apply(current => removeBand(current, key))}/>
                </aside>
            </div>
        </div>
    );
};
