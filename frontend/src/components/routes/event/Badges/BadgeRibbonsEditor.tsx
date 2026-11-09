import {ColorInput, Text, TextInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {BadgeRibbon, Product} from "../../../../types.ts";
import {BADGE_ACCENT_SWATCHES} from "../../../../utilites/badges.ts";
import classes from "./BadgeDesigner.module.scss";

interface BadgeRibbonsEditorProps {
    products: Product[];
    ribbons: BadgeRibbon[];
    accentColor: string;
    onChange: (ribbons: BadgeRibbon[]) => void;
    disabled?: boolean;
}

export const BadgeRibbonsEditor = ({products, ribbons, accentColor, onChange, disabled}: BadgeRibbonsEditorProps) => {
    if (!products.length) {
        return <Text size="sm" c="dimmed">{t`Create a ticket to give it a badge colour.`}</Text>;
    }

    const update = (productId: number, patch: Partial<BadgeRibbon>) => {
        const existing = ribbons.find((ribbon) => ribbon.product_id === productId);
        const next: BadgeRibbon = {
            product_id: productId,
            label: existing?.label ?? null,
            color: existing?.color ?? accentColor,
            ...patch,
        };
        onChange([...ribbons.filter((ribbon) => ribbon.product_id !== productId), next]);
    };

    return (
        <div className={classes.ribbons}>
            {products.map((product) => {
                const ribbon = ribbons.find((item) => item.product_id === product.id);
                return (
                    <div key={product.id} className={classes.ribbonRow}>
                        <ColorInput
                            className={classes.ribbonColor}
                            size="xs"
                            format="hex"
                            swatches={BADGE_ACCENT_SWATCHES}
                            swatchesPerRow={5}
                            value={ribbon?.color ?? accentColor}
                            onChange={(color) => update(product.id as number, {color})}
                            disabled={disabled}
                            aria-label={t`Colour for ${product.title}`}
                            withEyeDropper={false}
                        />
                        <TextInput
                            className={classes.ribbonLabel}
                            size="xs"
                            maxLength={40}
                            placeholder={product.title}
                            value={ribbon?.label ?? ''}
                            onChange={(event) => update(product.id as number, {label: event.currentTarget.value || null})}
                            disabled={disabled}
                            aria-label={t`Badge label for ${product.title}`}
                        />
                    </div>
                );
            })}
        </div>
    );
};
