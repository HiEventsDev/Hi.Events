import http from 'k6/http';
import {check} from 'k6';
import {Counter} from 'k6/metrics';

const BASE_URL = (__ENV.BASE_URL || 'https://localhost:8443/api').replace(/\/$/, '');
const EVENT_ID = __ENV.EVENT_ID;
const OCCURRENCE_ID = __ENV.OCCURRENCE_ID;
const RATE = Number(__ENV.RATE ?? 50);
const DURATION = __ENV.DURATION || '30s';

const rateLimited = new Counter('rate_limited');

export const options = {
    insecureSkipTLSVerify: __ENV.INSECURE !== 'false',
    scenarios: {
        event_page: {
            executor: 'constant-arrival-rate',
            rate: RATE,
            timeUnit: '1s',
            duration: DURATION,
            preAllocatedVUs: Math.max(20, RATE * 2),
            maxVUs: Math.max(100, RATE * 10),
        },
    },
    thresholds: {
        rate_limited: ['count==0'],
        http_req_failed: ['rate<0.01'],
        http_req_duration: ['p(95)<500'],
    },
};

export default function () {
    const query = OCCURRENCE_ID ? `?event_occurrence_id=${OCCURRENCE_ID}` : '';
    const res = http.get(`${BASE_URL}/public/events/${EVENT_ID}${query}`, {
        headers: {Accept: 'application/json'},
    });

    if (res.status === 429) {
        rateLimited.add(1);
    }

    check(res, {
        'status is 200': (r) => r.status === 200,
        'has product categories': (r) => Array.isArray(r.json('data.product_categories')),
    });
}
