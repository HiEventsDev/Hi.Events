import http from 'k6/http';
import {check, fail} from 'k6';
import {Counter, Trend} from 'k6/metrics';
import {randomIntBetween, uuidv4} from 'https://jslib.k6.io/k6-utils/1.4.0/index.js';
import {textSummary} from 'https://jslib.k6.io/k6-summary/0.1.0/index.js';

const BASE_URL = (__ENV.BASE_URL || 'https://localhost:8443/api').replace(/\/$/, '');
const EVENT_ID = __ENV.EVENT_ID;
const TOKEN = __ENV.TOKEN;
const CAPACITY = __ENV.CAPACITY ? Number(__ENV.CAPACITY) : null;
const ABANDON_RATIO = Number(__ENV.ABANDON_RATIO ?? 0.1);
const MAX_QTY = Number(__ENV.MAX_QTY ?? 2);
const RATE = Number(__ENV.RATE ?? 30);
const DURATION = __ENV.DURATION || '1m';
const SCENARIO = __ENV.SCENARIO || 'spike';

const JSON_HEADERS = {'Content-Type': 'application/json', Accept: 'application/json'};

const ordersReserved = new Counter('orders_reserved');
const ordersCompleted = new Counter('orders_completed');
const ordersAbandoned = new Counter('orders_abandoned');
const ticketsCompleted = new Counter('tickets_completed');
const soldOut = new Counter('sold_out_responses');
const rateLimited = new Counter('rate_limited');
const serverErrors = new Counter('server_errors');
const unexpected = new Counter('unexpected_responses');

const tEvent = new Trend('step_event_page', true);
const tReserve = new Trend('step_reserve', true);
const tComplete = new Trend('step_complete', true);
const tOrder = new Trend('step_order_get', true);

const scenarios = {
    smoke: {
        executor: 'per-vu-iterations',
        vus: 1,
        iterations: 3,
    },
    steady: {
        executor: 'constant-arrival-rate',
        rate: RATE,
        timeUnit: '1s',
        duration: DURATION,
        preAllocatedVUs: Math.max(20, RATE * 2),
        maxVUs: Math.max(100, RATE * 10),
    },
    spike: {
        executor: 'ramping-arrival-rate',
        startRate: 1,
        timeUnit: '1s',
        preAllocatedVUs: Math.max(20, RATE * 2),
        maxVUs: Math.max(100, RATE * 10),
        stages: [
            {target: RATE, duration: '20s'},
            {target: RATE, duration: DURATION},
            {target: 0, duration: '20s'},
        ],
    },
};

export const options = {
    insecureSkipTLSVerify: __ENV.INSECURE !== 'false',
    scenarios: {[SCENARIO]: scenarios[SCENARIO]},
    thresholds: {
        rate_limited: ['count==0'],
        server_errors: ['count==0'],
        unexpected_responses: ['count==0'],
        step_event_page: ['p(95)<800'],
        step_reserve: ['p(95)<1500'],
        step_complete: ['p(95)<1500'],
        step_order_get: ['p(95)<800'],
        http_req_failed: ['rate<0.01'],
    },
};

http.setResponseCallback(http.expectedStatuses(200, 201, 204, 422));

function publicEvent(occurrenceId) {
    const query = occurrenceId ? `?event_occurrence_id=${occurrenceId}` : '';
    return http.get(`${BASE_URL}/public/events/${EVENT_ID}${query}`, {
        headers: JSON_HEADERS,
        tags: {step: 'event_page'},
    });
}

function createCappedProduct() {
    const auth = {...JSON_HEADERS, Authorization: `Bearer ${TOKEN}`};
    const categories = http.get(`${BASE_URL}/events/${EVENT_ID}/product-categories`, {headers: auth});
    if (categories.status !== 200) {
        fail(`Could not list product categories (${categories.status}): ${categories.body}`);
    }
    const categoryId = categories.json('data.0.id');

    const res = http.post(`${BASE_URL}/events/${EVENT_ID}/products`, JSON.stringify({
        title: `k6 load test ${new Date().toISOString()}`,
        type: 'FREE',
        product_type: 'TICKET',
        product_category_id: categoryId,
        max_per_order: MAX_QTY,
        prices: [{price: 0, initial_quantity_available: CAPACITY}],
    }), {headers: auth});

    if (res.status !== 201) {
        fail(`Could not create capped product (${res.status}): ${res.body}`);
    }

    return {productId: res.json('data.id'), priceId: res.json('data.prices.0.id')};
}

function discoverFreeProduct(event) {
    const categories = event.product_categories || [];
    for (const category of categories) {
        for (const product of category.products || []) {
            const price = (product.prices || [])[0];
            if (product.type === 'FREE' && price && !product.is_sold_out && !product.is_addon_only) {
                return {productId: product.id, priceId: price.id, quantityAvailable: product.quantity_available ?? null};
            }
        }
    }
    return null;
}

function discoverOccurrence(event) {
    if (__ENV.OCCURRENCE_ID) {
        return Number(__ENV.OCCURRENCE_ID);
    }
    const occurrence = (event.occurrences || []).find((o) => o.is_active && !o.is_past);
    return occurrence ? occurrence.id : null;
}

export function setup() {
    if (!EVENT_ID) {
        fail('EVENT_ID is required');
    }

    const res = publicEvent(null);
    if (res.status !== 200) {
        fail(`Could not load public event ${EVENT_ID} (${res.status}): ${res.body}`);
    }
    const event = res.json('data');

    let product;
    if (__ENV.PRODUCT_ID && __ENV.PRICE_ID) {
        product = {productId: Number(__ENV.PRODUCT_ID), priceId: Number(__ENV.PRICE_ID)};
    } else if (TOKEN && CAPACITY) {
        product = createCappedProduct();
    } else {
        product = discoverFreeProduct(event);
    }

    if (!product) {
        fail('No free, in-stock product found on the event. Pass PRODUCT_ID/PRICE_ID, or TOKEN + CAPACITY to create one.');
    }

    const target = {
        eventId: Number(EVENT_ID),
        occurrenceId: discoverOccurrence(event),
        ...product,
    };

    console.log(`Target: ${JSON.stringify(target)}`);
    return target;
}

function classify(res, counterOnOk) {
    if (res.status === 429) {
        rateLimited.add(1);
        return false;
    }
    if (res.status >= 500) {
        serverErrors.add(1);
        return false;
    }
    if (res.status === 422) {
        const body = res.body || '';
        if (/sold out|maximum number/i.test(body)) {
            soldOut.add(1);
            return false;
        }
    }
    if (counterOnOk.includes(res.status)) {
        return true;
    }
    unexpected.add(1);
    console.error(`Unexpected ${res.request.method} ${res.url} -> ${res.status}: ${String(res.body).slice(0, 300)}`);
    return false;
}

export default function (target) {
    const {eventId, occurrenceId, productId, priceId} = target;
    http.cookieJar().clear(BASE_URL);

    const eventRes = publicEvent(occurrenceId);
    tEvent.add(eventRes.timings.duration);
    if (!classify(eventRes, [200])) {
        return;
    }

    const quantity = randomIntBetween(1, MAX_QTY);
    const line = {product_id: productId, quantities: [{price_id: priceId, quantity}]};
    if (occurrenceId) {
        line.event_occurrence_id = occurrenceId;
    }

    const reserveRes = http.post(
        `${BASE_URL}/public/events/${eventId}/order`,
        JSON.stringify({products: [line]}),
        {headers: JSON_HEADERS, tags: {step: 'reserve'}},
    );
    tReserve.add(reserveRes.timings.duration);
    if (!classify(reserveRes, [201])) {
        return;
    }
    ordersReserved.add(1);

    const order = reserveRes.json('data');
    const session = order.session_identifier;
    const orderUrl = `${BASE_URL}/public/events/${eventId}/order/${order.short_id}`;

    if (Math.random() < ABANDON_RATIO) {
        const abandonRes = http.post(`${orderUrl}/abandon?session_identifier=${session}`, null, {
            headers: JSON_HEADERS,
            tags: {step: 'abandon'},
        });
        if (classify(abandonRes, [200, 204])) {
            ordersAbandoned.add(1);
        }
        return;
    }

    const email = `k6-${uuidv4()}@load.test`;
    const buyer = {first_name: 'Load', last_name: 'Test', email, email_confirmation: email};
    const attendees = order.order_items.flatMap((item) =>
        Array.from({length: item.quantity}, () => ({
            product_id: item.product_id,
            product_price_id: item.product_price_id,
            ...buyer,
        })),
    );

    const completeRes = http.put(
        `${orderUrl}?session_identifier=${session}`,
        JSON.stringify({order: buyer, products: attendees}),
        {headers: JSON_HEADERS, tags: {step: 'complete'}},
    );
    tComplete.add(completeRes.timings.duration);
    if (!classify(completeRes, [200])) {
        return;
    }

    const completed = check(completeRes, {
        'order completed': (r) => r.json('data.status') === 'COMPLETED',
    });
    if (completed) {
        ordersCompleted.add(1);
        ticketsCompleted.add(attendees.length);
    }

    const orderRes = http.get(`${orderUrl}?session_identifier=${session}&include=event`, {
        headers: JSON_HEADERS,
        tags: {step: 'order_get'},
    });
    tOrder.add(orderRes.timings.duration);
    classify(orderRes, [200]);
}

export function teardown(target) {
    const res = publicEvent(target.occurrenceId);
    if (res.status !== 200) {
        console.error(`Teardown: could not reload event (${res.status})`);
        return;
    }
    const event = res.json('data');
    for (const category of event.product_categories || []) {
        const product = (category.products || []).find((p) => p.id === target.productId);
        if (product) {
            console.log(`After run: product ${product.id} quantity_available=${product.quantity_available ?? 'unlimited'} is_sold_out=${product.is_sold_out}`);
        }
    }

    if (TOKEN) {
        const auth = {...JSON_HEADERS, Authorization: `Bearer ${TOKEN}`};
        const product = http.get(`${BASE_URL}/events/${target.eventId}/products/${target.productId}`, {headers: auth});
        if (product.status === 200) {
            const price = (product.json('data.prices') || []).find((p) => p.id === target.priceId);
            const sold = price?.quantity_sold ?? product.json('data.quantity_sold');
            const cap = price?.initial_quantity_available ?? null;
            console.log(`After run (authoritative): price ${target.priceId} quantity_sold=${sold} initial_quantity_available=${cap ?? 'unlimited'}`);
            check(product, {'no oversell (quantity_sold <= capacity)': () => cap === null || sold <= cap});
        }
    }
}

export function handleSummary(data) {
    const count = (name) => data.metrics[name]?.values?.count ?? 0;
    const p95 = (name) => Math.round(data.metrics[name]?.values?.['p(95)'] ?? 0);

    const lines = [
        '',
        '=== Checkout funnel summary ===',
        `orders reserved:      ${count('orders_reserved')}`,
        `orders completed:     ${count('orders_completed')}`,
        `orders abandoned:     ${count('orders_abandoned')}`,
        `tickets completed:    ${count('tickets_completed')}${CAPACITY ? ` (capacity ${CAPACITY})` : ''}`,
        `sold-out responses:   ${count('sold_out_responses')}`,
        `429 rate limited:     ${count('rate_limited')}`,
        `5xx server errors:    ${count('server_errors')}`,
        `unexpected responses: ${count('unexpected_responses')}`,
        `p95 event page:       ${p95('step_event_page')} ms`,
        `p95 reserve:          ${p95('step_reserve')} ms`,
        `p95 complete:         ${p95('step_complete')} ms`,
        `p95 order get:        ${p95('step_order_get')} ms`,
    ];

    if (CAPACITY && count('tickets_completed') > CAPACITY) {
        lines.push(`!!! OVERSOLD: ${count('tickets_completed')} tickets completed against a capacity of ${CAPACITY}`);
    }

    lines.push('');
    return {stdout: textSummary(data, {indent: ' ', enableColors: true}) + '\n' + lines.join('\n') + '\n'};
}
