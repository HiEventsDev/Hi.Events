import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
    vus: 50,
    duration: '60s', // Using '60s' instead of '60000' for clarity
};

export default function () {
    let res = http.get('https://api.hi.events/public/events/1', {
        headers: {
            'Accept': 'application/json',
        },
    });

    check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 400ms': (r) => r.timings.duration < 400,
        'response is JSON': (r) => r.headers['Content-Type'] && r.headers['Content-Type'].includes('application/json'),
    });

    sleep(1);
}
