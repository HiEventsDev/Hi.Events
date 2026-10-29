import {useEffect, useMemo, useState} from "react";
import {Link, useParams} from "react-router";
import {useForm} from "@mantine/form";
import {useDisclosure} from "@mantine/hooks";
import {
    ActionIcon,
    Button,
    ColorInput,
    Group,
    NumberInput,
    SegmentedControl,
    Select,
    Switch,
    Text,
    Textarea,
    TextInput,
    Tooltip,
} from "@mantine/core";
import {
    IconColorSwatch,
    IconLayoutGrid,
    IconListDetails,
    IconPhoto,
    IconPrinter,
    IconRuler2,
    IconTag,
    IconTextSize,
    IconToggleRight,
    IconCards,
    IconDoorEnter,
} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {BadgeDesign, BadgeDesignLine, BadgeSheet, BadgeSize, ProductType, Question} from "../../../../types.ts";
import {useGetBadgeDesign} from "../../../../queries/useGetBadgeDesign.ts";
import {useGetBadgeStats} from "../../../../queries/useGetBadgeStats.ts";
import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {GET_EVENT_IMAGES_QUERY_KEY, useGetEventImages} from "../../../../queries/useGetEventImages.ts";
import {useGetEventQuestions} from "../../../../queries/useGetEventQuestions.ts";
import {useGetMe} from "../../../../queries/useGetMe.ts";
import {useUpdateBadgeDesign} from "../../../../mutations/useUpdateBadgeDesign.ts";
import {useBadgesPrintedSync} from "../../../../hooks/useBadgesPrintedSync.ts";
import {useCreateQuestion} from "../../../../mutations/useCreateQuestion.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {queryClient} from "../../../../utilites/queryClient.ts";
import {getProductsFromEvent} from "../../../../utilites/helpers.ts";
import {
    BADGE_ACCENT_SWATCHES,
    BADGE_SIZE_DIMENSIONS,
    computeSheetGrid,
    DEFAULT_BADGE_DESIGN,
    getBadgeBackModeLabel,
    getBadgeDimensions,
    getBadgeImages,
    getBadgeSheetLabel,
    getBadgeSizeLabel,
    getDesignBackPrinting,
    getPrintUnitSize,
    MAX_BADGE_LINES,
    openBadgePrintWindow,
} from "../../../../utilites/badges.ts";
import {LoadingMask} from "../../../common/LoadingMask";
import {DesignerSection, DesignerShell} from "../../../common/DesignerShell";
import {ImageUploadDropzone} from "../../../common/ImageUploadDropzone";
import {ThemeFontControl} from "../../../common/ThemeFontControl";
import {ScaledNameBadge} from "../../../common/NameBadge/ScaledNameBadge.tsx";
import {PrintBadgesModal} from "../../../modals/PrintBadgesModal";
import {TemplatePicker} from "./TemplatePicker.tsx";
import {BadgeLinesEditor} from "./BadgeLinesEditor.tsx";
import {BadgeRibbonsEditor} from "./BadgeRibbonsEditor.tsx";
import {buildSampleBadge, COMPANY_QUESTION_PATTERN, JOB_TITLE_QUESTION_PATTERN} from "./sampleBadge.ts";
import classes from "./BadgeDesigner.module.scss";

const SIZE_OPTIONS: BadgeSize[] = [
    ...(Object.keys(BADGE_SIZE_DIMENSIONS) as BadgeSize[]),
    'CUSTOM',
];

const SHEET_OPTIONS: BadgeSheet[] = ['A4', 'LETTER', 'NONE'];

const designFromResponse = (design: BadgeDesign): BadgeDesign => {
    const {is_customized: _ignored, ...rest} = design;
    return rest;
};

const BadgeDesigner = () => {
    const {eventId} = useParams();
    useBadgesPrintedSync(eventId);
    const designQuery = useGetBadgeDesign(eventId);
    const eventQuery = useGetEvent(eventId);
    const imagesQuery = useGetEventImages(eventId);
    const questionsQuery = useGetEventQuestions(eventId);
    const statsQuery = useGetBadgeStats(eventId);
    const meQuery = useGetMe();
    const updateMutation = useUpdateBadgeDesign();
    const createQuestionMutation = useCreateQuestion();
    const formErrorHandle = useFormErrorResponseHandler();
    const form = useForm<BadgeDesign>({initialValues: DEFAULT_BADGE_DESIGN});
    const [previewSide, setPreviewSide] = useState<'front' | 'back'>('front');
    const [previewProductId, setPreviewProductId] = useState<string | null>(null);
    const [longName, setLongName] = useState(false);
    const [printModalOpen, printModal] = useDisclosure(false);

    useEffect(() => {
        if (designQuery.data && !form.isDirty()) {
            const values = designFromResponse(designQuery.data);
            form.setValues(values);
            form.resetDirty(values);
        }
    }, [designQuery.data]);

    const event = eventQuery.data;
    const design = form.values;
    const questions: Question[] = questionsQuery.data ?? [];
    const ticketProducts = useMemo(
        () => (getProductsFromEvent(event) ?? []).filter((product) => product.product_type === ProductType.Ticket),
        [event],
    );
    const previewProduct = ticketProducts.find((product) => String(product.id) === previewProductId) ?? ticketProducts[0];
    const images = getBadgeImages(imagesQuery.data);
    const badgeImage = (type: 'BADGE_LOGO' | 'BADGE_ARTWORK' | 'BADGE_SPONSORS') => imagesQuery.data?.find((image) => image.type === type);

    const sampleBadge = buildSampleBadge({
        design,
        questions,
        product: previewProduct,
        firstName: meQuery.data?.first_name,
        lastName: meQuery.data?.last_name,
        longName,
        seatLabel: event?.has_seat_map ? t`Row C · 14` : null,
    });

    const hasCompanyQuestion = questions.some((question) => COMPANY_QUESTION_PATTERN.test(question.title));
    const hasJobTitleQuestion = questions.some((question) => JOB_TITLE_QUESTION_PATTERN.test(question.title));

    const backPrinting = getDesignBackPrinting(design);
    const printUnit = getPrintUnitSize(getBadgeDimensions(design), backPrinting);
    const grid = computeSheetGrid(printUnit, design.sheet);
    const foldWidth = Math.round(printUnit.width);
    const foldHeight = Math.round(printUnit.height);

    const handleSubmit = (values: BadgeDesign) => {
        updateMutation.mutate({eventId, design: values}, {
            onSuccess: () => {
                form.resetDirty(values);
                showSuccess(t`Badge design saved`);
            },
            onError: (error) => formErrorHandle(form, error),
        });
    };

    const handleImageChange = () => queryClient.invalidateQueries({queryKey: [GET_EVENT_IMAGES_QUERY_KEY, eventId]});

    const addCompanyQuestions = async () => {
        const productIds = ticketProducts.map((product) => String(product.id));
        const wanted = [
            !hasCompanyQuestion && {title: t`Company`, size: 'M' as const},
            !hasJobTitleQuestion && {title: t`Job title`, size: 'S' as const},
        ].filter(Boolean) as { title: string; size: 'M' | 'S' }[];

        try {
            const created: BadgeDesignLine[] = [];
            for (const question of wanted) {
                const response = await createQuestionMutation.mutateAsync({
                    eventId,
                    question: {
                        title: question.title,
                        type: 'SINGLE_LINE_TEXT',
                        required: false,
                        is_hidden: false,
                        options: [],
                        belongs_to: 'PRODUCT',
                        product_ids: productIds,
                    },
                });
                created.push({source: 'QUESTION', question_id: Number(response.data.id), size: question.size});
            }
            const currentLines = form.getValues().lines;
            const added = created.slice(0, Math.max(0, MAX_BADGE_LINES - currentLines.length));
            form.setFieldValue('lines', [...currentLines, ...added]);
            showSuccess(added.length === created.length
                ? t`Questions added to checkout and to the badge`
                : t`Questions added to checkout. Remove a line from the badge to show them.`);
        } catch {
            showError(t`We couldn't add the questions. Please try again.`);
        }
    };

    if (designQuery.isLoading || eventQuery.isLoading || imagesQuery.isLoading) {
        return <LoadingMask/>;
    }

    const isDisabled = updateMutation.isPending;
    const hasUnsavedChanges = form.isDirty();
    const stats = statsQuery.data;

    return (
        <>
            <DesignerShell
                title={t`Badge Design`}
                hasUnsavedChanges={hasUnsavedChanges}
                isSaving={updateMutation.isPending}
                onSave={() => form.onSubmit(handleSubmit)()}
                onDiscard={() => form.reset()}
                previewActions={(
                    <Group gap={6} wrap="nowrap" className={classes.previewActions}>
                        {design.back_mode !== 'NONE' && (
                            <SegmentedControl
                                size="xs"
                                value={previewSide}
                                onChange={(value) => setPreviewSide(value as 'front' | 'back')}
                                data={[{value: 'front', label: t`Front`}, {value: 'back', label: t`Back`}]}
                            />
                        )}
                        {ticketProducts.length > 1 && (
                            <Select
                                size="xs"
                                className={classes.previewTicket}
                                value={String(previewProduct?.id ?? '')}
                                onChange={setPreviewProductId}
                                data={ticketProducts.map((product) => ({value: String(product.id), label: product.title}))}
                                allowDeselect={false}
                                aria-label={t`Preview ticket type`}
                                comboboxProps={{withinPortal: true}}
                            />
                        )}
                        <Tooltip label={longName ? t`Show a typical name` : t`Try a very long name`} withArrow>
                            <ActionIcon
                                variant={longName ? 'filled' : 'default'}
                                size="input-xs"
                                onClick={() => setLongName((value) => !value)}
                                aria-label={t`Try a very long name`}
                                aria-pressed={longName}
                            >
                                <IconTextSize size={16}/>
                            </ActionIcon>
                        </Tooltip>
                        <Tooltip label={hasUnsavedChanges ? t`Test print uses your last saved design` : t`Print a sample badge`} withArrow>
                            <Button
                                size="xs"
                                variant="default"
                                onClick={() => openBadgePrintWindow(eventId, {sample: true})}
                            >
                                {t`Test print`}
                            </Button>
                        </Tooltip>
                        <Button
                            size="xs"
                            leftSection={<IconPrinter size={14}/>}
                            onClick={printModal.open}
                            data-testid="badge-designer-print-button"
                        >
                            {t`Print badges`}
                        </Button>
                    </Group>
                )}
                preview={(
                    <div className={classes.previewStage}>
                        <div className={classes.previewBadge} data-testid="badge-designer-preview">
                            <ScaledNameBadge
                                layout={design}
                                badge={sampleBadge}
                                eventName={event?.title ?? ''}
                                images={images}
                                timezone={event?.timezone}
                                side={design.back_mode === 'NONE' ? 'front' : previewSide}
                            />
                        </div>
                        {stats && stats.total > 0 && (
                            <Text size="xs" c="dimmed" className={classes.previewStats} data-testid="badge-designer-stats">
                                {t`${stats.printed} of ${stats.total} badges printed`}
                                {stats.needs_reprint > 0 && ` · ${t`${stats.needs_reprint} need reprinting`}`}
                            </Text>
                        )}
                    </div>
                )}
            >
                <DesignerSection icon={<IconLayoutGrid/>} title={t`Template`}>
                    <TemplatePicker
                        design={design}
                        badge={sampleBadge}
                        eventName={event?.title ?? ''}
                        images={images}
                        onSelect={(template) => {
                            if (template !== design.template) {
                                form.setValues({template, background_color: null, text_color: null});
                            }
                        }}
                    />
                </DesignerSection>

                <DesignerSection icon={<IconListDetails/>} title={t`What's on the badge`}>
                    <BadgeLinesEditor
                        lines={design.lines}
                        questions={questions}
                        disabled={isDisabled}
                        onChange={(lines) => form.setFieldValue('lines', lines)}
                        suggestion={!hasCompanyQuestion || !hasJobTitleQuestion ? {
                            label: !hasCompanyQuestion && !hasJobTitleQuestion
                                ? t`Collect company and job title`
                                : (!hasCompanyQuestion ? t`Collect company name` : t`Collect job title`),
                            onAdd: addCompanyQuestions,
                            loading: createQuestionMutation.isPending,
                        } : undefined}
                    />
                </DesignerSection>

                <DesignerSection
                    icon={<IconTag/>}
                    title={t`Ticket colours`}
                    action={(
                        <Switch
                            size="xs"
                            checked={design.show_ribbon}
                            onChange={(e) => form.setFieldValue('show_ribbon', e.currentTarget.checked)}
                            aria-label={t`Show ticket colour band`}
                        />
                    )}
                >
                    <Text size="xs" c="dimmed" mb="sm">
                        {t`Give speakers, sponsors, staff and attendees their own colour and label so they're easy to spot.`}
                    </Text>
                    {design.show_ribbon && (
                        <BadgeRibbonsEditor
                            products={ticketProducts}
                            ribbons={design.ribbons}
                            accentColor={design.accent_color}
                            disabled={isDisabled}
                            onChange={(ribbons) => form.setFieldValue('ribbons', ribbons)}
                        />
                    )}
                </DesignerSection>

                <DesignerSection icon={<IconRuler2/>} title={t`Size and paper`}>
                    <div className={classes.stack}>
                        <Select
                            label={t`Badge size`}
                            size="sm"
                            data={SIZE_OPTIONS.map((size) => ({value: size, label: getBadgeSizeLabel(size)}))}
                            allowDeselect={false}
                            disabled={isDisabled}
                            {...form.getInputProps('size')}
                            onChange={(value) => {
                                form.setFieldValue('size', value as BadgeSize);
                                if (value === 'CUSTOM' && !form.values.custom_width_mm) {
                                    form.setFieldValue('custom_width_mm', 90);
                                    form.setFieldValue('custom_height_mm', 120);
                                }
                            }}
                            data-testid="badge-size-select"
                        />
                        {design.size === 'CUSTOM' && (
                            <div className={classes.twoColumns}>
                                <NumberInput
                                    label={t`Width (mm)`}
                                    size="sm"
                                    min={40}
                                    max={220}
                                    disabled={isDisabled}
                                    {...form.getInputProps('custom_width_mm')}
                                />
                                <NumberInput
                                    label={t`Height (mm)`}
                                    size="sm"
                                    min={40}
                                    max={220}
                                    disabled={isDisabled}
                                    {...form.getInputProps('custom_height_mm')}
                                />
                            </div>
                        )}
                        <Select
                            label={t`Paper for batch printing`}
                            size="sm"
                            data={SHEET_OPTIONS.map((sheet) => ({value: sheet, label: getBadgeSheetLabel(sheet)}))}
                            allowDeselect={false}
                            disabled={isDisabled}
                            {...form.getInputProps('sheet')}
                        />
                        <Text size="xs" c="dimmed" data-testid="badge-paper-summary">
                            {backPrinting === 'FOLD_OVER'
                                ? (design.sheet === 'NONE' || grid.perPage === 1
                                    ? t`Each fold-over badge prints on its own ${foldWidth} × ${foldHeight} mm page.`
                                    : t`${grid.perPage} fold-over badges per sheet (${grid.columns} × ${grid.rows}). Cut along the guides, then fold each one in half.`)
                                : (design.sheet === 'NONE' || grid.perPage === 1
                                    ? t`Each badge prints on its own page, for card and label printers.`
                                    : t`${grid.perPage} badges per sheet (${grid.columns} × ${grid.rows}). Use matching badge inserts or cut along the guides.`)}
                        </Text>
                    </div>
                </DesignerSection>

                <DesignerSection icon={<IconColorSwatch/>} title={t`Colours and type`}>
                    <div className={classes.stack}>
                        <ColorInput
                            label={t`Brand colour`}
                            size="sm"
                            format="hex"
                            swatches={BADGE_ACCENT_SWATCHES}
                            swatchesPerRow={10}
                            disabled={isDisabled}
                            {...form.getInputProps('accent_color')}
                            data-testid="badge-accent-color-input"
                        />
                        <div className={classes.twoColumns}>
                            <ColorInput
                                label={t`Background`}
                                size="sm"
                                format="hex"
                                placeholder={t`Automatic`}
                                disabled={isDisabled || design.template === 'BOLD'}
                                value={design.background_color ?? ''}
                                onChange={(value) => form.setFieldValue('background_color', value || null)}
                            />
                            <ColorInput
                                label={t`Text`}
                                size="sm"
                                format="hex"
                                placeholder={t`Automatic`}
                                disabled={isDisabled}
                                value={design.text_color ?? ''}
                                onChange={(value) => form.setFieldValue('text_color', value || null)}
                            />
                        </div>
                        <div>
                            <Text size="sm" fw={500} mb={4}>{t`Font`}</Text>
                            <ThemeFontControl
                                value={design.font_family}
                                onChange={(font) => form.setFieldValue('font_family', font)}
                                disabled={isDisabled}
                            />
                        </div>
                        <div>
                            <Text size="sm" fw={500} mb={4}>{t`Alignment`}</Text>
                            <SegmentedControl
                                fullWidth
                                size="xs"
                                value={design.text_align ?? 'AUTO'}
                                onChange={(value) => form.setFieldValue('text_align', value === 'AUTO' ? null : value as 'LEFT' | 'CENTER')}
                                data={[
                                    {value: 'AUTO', label: t`Template default`},
                                    {value: 'LEFT', label: t`Left`},
                                    {value: 'CENTER', label: t`Centre`},
                                ]}
                            />
                        </div>
                        <Switch
                            label={t`Names in capitals`}
                            checked={design.uppercase_names}
                            onChange={(e) => form.setFieldValue('uppercase_names', e.currentTarget.checked)}
                            disabled={isDisabled}
                        />
                    </div>
                </DesignerSection>

                <DesignerSection icon={<IconPhoto/>} title={t`Images`}>
                    <div className={classes.stack}>
                        <div>
                            <Text size="sm" fw={500}>{t`Logo`}</Text>
                            <Text size="xs" c="dimmed" mb="xs">
                                {t`Transparent PNG works best. Your ticket logo is used if you leave this empty.`}
                            </Text>
                            <ImageUploadDropzone
                                imageType="BADGE_LOGO"
                                entityId={eventId}
                                onUploadSuccess={handleImageChange}
                                onDeleteSuccess={handleImageChange}
                                existingImageData={{url: badgeImage('BADGE_LOGO')?.url, id: badgeImage('BADGE_LOGO')?.id}}
                                displayMode="compact"
                            />
                        </div>
                        <div>
                            <Text size="sm" fw={500}>{t`Artwork`}</Text>
                            <Text size="xs" c="dimmed" mb="xs">
                                {t`Fills the header on the Banner template and the background on Artwork.`}
                            </Text>
                            <ImageUploadDropzone
                                imageType="BADGE_ARTWORK"
                                entityId={eventId}
                                onUploadSuccess={handleImageChange}
                                onDeleteSuccess={handleImageChange}
                                existingImageData={{url: badgeImage('BADGE_ARTWORK')?.url, id: badgeImage('BADGE_ARTWORK')?.id}}
                                displayMode="compact"
                            />
                        </div>
                        <div>
                            <Text size="sm" fw={500}>{t`Sponsor strip`}</Text>
                            <Text size="xs" c="dimmed" mb="xs">
                                {t`A wide image of sponsor logos, printed near the bottom of every badge.`}
                            </Text>
                            <ImageUploadDropzone
                                imageType="BADGE_SPONSORS"
                                entityId={eventId}
                                onUploadSuccess={handleImageChange}
                                onDeleteSuccess={handleImageChange}
                                existingImageData={{url: badgeImage('BADGE_SPONSORS')?.url, id: badgeImage('BADGE_SPONSORS')?.id}}
                                displayMode="compact"
                            />
                        </div>
                    </div>
                </DesignerSection>

                <DesignerSection icon={<IconToggleRight/>} title={t`Details`}>
                    <div className={classes.stack}>
                        <Switch
                            label={t`QR code`}
                            description={t`Scans at any check-in list, the same as the ticket`}
                            checked={design.show_qr}
                            onChange={(e) => form.setFieldValue('show_qr', e.currentTarget.checked)}
                            disabled={isDisabled}
                        />
                        <Switch
                            label={t`Event name`}
                            checked={design.show_event_name}
                            onChange={(e) => form.setFieldValue('show_event_name', e.currentTarget.checked)}
                            disabled={isDisabled}
                        />
                        <Switch
                            label={t`Logo`}
                            checked={design.show_logo}
                            onChange={(e) => form.setFieldValue('show_logo', e.currentTarget.checked)}
                            disabled={isDisabled}
                        />
                        <Switch
                            label={t`Sponsor strip`}
                            checked={design.show_sponsors}
                            onChange={(e) => form.setFieldValue('show_sponsors', e.currentTarget.checked)}
                            disabled={isDisabled}
                        />
                        <Switch
                            label={t`Reserved seat`}
                            description={t`Only shown for attendees with a seat`}
                            checked={design.show_seat}
                            onChange={(e) => form.setFieldValue('show_seat', e.currentTarget.checked)}
                            disabled={isDisabled}
                        />
                        <TextInput
                            label={t`Footer text`}
                            size="sm"
                            maxLength={120}
                            placeholder={t`For example: #DevConf2027`}
                            disabled={isDisabled}
                            value={design.footer_text ?? ''}
                            onChange={(e) => form.setFieldValue('footer_text', e.currentTarget.value || null)}
                        />
                    </div>
                </DesignerSection>

                <DesignerSection icon={<IconCards/>} title={t`Back of the badge`}>
                    <div className={classes.stack}>
                        <Select
                            size="sm"
                            data={(['NONE', 'SAME', 'INFO'] as const).map((mode) => ({value: mode, label: getBadgeBackModeLabel(mode)}))}
                            allowDeselect={false}
                            disabled={isDisabled}
                            {...form.getInputProps('back_mode')}
                            aria-label={t`Back of the badge`}
                            data-testid="badge-back-mode-select"
                        />
                        {design.back_mode !== 'NONE' && (
                            <div>
                                <Text size="sm" fw={500} mb={4}>{t`How the back is printed`}</Text>
                                <SegmentedControl
                                    fullWidth
                                    size="xs"
                                    value={design.fold_over ? 'FOLD_OVER' : 'DUPLEX'}
                                    onChange={(value) => form.setFieldValue('fold_over', value === 'FOLD_OVER')}
                                    disabled={isDisabled}
                                    data={[
                                        {value: 'DUPLEX', label: t`Two-sided printer`},
                                        {value: 'FOLD_OVER', label: t`Fold-over`},
                                    ]}
                                    data-testid="badge-back-printing-control"
                                />
                            </div>
                        )}
                        <Text size="xs" c="dimmed">
                            {backPrinting === 'NONE' && t`Badges on a lanyard often flip around. Print both sides so names always face out.`}
                            {backPrinting === 'DUPLEX' && t`Turn on double-sided printing (flip on long edge) in the print dialog.`}
                            {backPrinting === 'FOLD_OVER' && t`The front and back print side by side on one side of the paper. Fold in half and both sides face out. No two-sided printer needed.`}
                        </Text>
                        {design.back_mode === 'INFO' && (
                            <Textarea
                                label={t`Back text`}
                                placeholder={[
                                    t`Wi-Fi: DevConf / password`,
                                    t`Agenda: devconf.example/agenda`,
                                    t`Emergency: +353 1 234 5678`,
                                ].join('\n')}
                                autosize
                                minRows={4}
                                maxLength={600}
                                disabled={isDisabled}
                                value={design.back_text ?? ''}
                                onChange={(e) => form.setFieldValue('back_text', e.currentTarget.value || null)}
                            />
                        )}
                    </div>
                </DesignerSection>

                <DesignerSection icon={<IconDoorEnter/>} title={t`Printing at the door`}>
                    <Text size="xs" c="dimmed" mb="sm">
                        {t`Turn on badge printing for a check-in list and door staff can print or reprint badges as people arrive. A laptop with a label printer works as a self check-in kiosk.`}
                    </Text>
                    <Button
                        component={Link}
                        to={`/manage/event/${eventId}/check-in`}
                        size="compact-sm"
                        variant="light"
                    >
                        {t`Manage check-in lists`}
                    </Button>
                </DesignerSection>
            </DesignerShell>

            {printModalOpen && <PrintBadgesModal eventId={eventId as string} onClose={printModal.close}/>}
        </>
    );
};

export default BadgeDesigner;
