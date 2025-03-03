import { Event, Order } from "../../../../../../types.ts";
import { Card } from "../../../../../common/Card";
import {getClientLocale} from "../../../../../../locales";
import { t } from "@lingui/macro";
import { Liquid } from 'liquidjs';
import React from 'react';

interface OfflinePaymentMethodProps {
    event: Event;
    order?: Order;
}

export const OfflinePaymentMethod = ({ event, order }: OfflinePaymentMethodProps) => {
    const eventSettings = event?.settings;
    // Initialize Liquid engine with default settings
    const engine = new Liquid();

    const replaceVariables = async (text: string) => {
        if (!text || !order) return text;

        const variables = {
            order_short_id: order.short_id,
            order_public_id: order.public_id,
            order_first_name: order.first_name,
            order_last_name: order.last_name,
            order_email: order.email,
            order_total_gross: order.total_gross,
            order_currency: order.currency,
            order_items: order.order_items,
            client_language: getClientLocale()
        };

        try {
            return await engine.parseAndRender(text, variables);
        } catch (error) {
            console.error('Error processing Liquid template:', error);
            return text;
        }
    };

    const [processedInstructions, setProcessedInstructions] = React.useState(eventSettings?.offline_payment_instructions || "");

    React.useEffect(() => {
        const processInstructions = async () => {
            const processed = await replaceVariables(eventSettings?.offline_payment_instructions || "");
            setProcessedInstructions(processed);
        };
        processInstructions();
    }, [eventSettings?.offline_payment_instructions, order]);

    return (
        <div>
            <h2>{t`Payment Instructions`}</h2>
            <Card>
                <div
                    dangerouslySetInnerHTML={{
                        __html: processedInstructions,
                    }}
                />
            </Card>
        </div>
    );
};
