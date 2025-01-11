import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
    vus: 50,
    duration: '60',
};

export default function () {
    let res = http.get('https://demo.hi.events/event/1/dog-conf-2030');

    // Check if the response status is 200 (OK)
    check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 200ms': (r) => r.timings.duration < 400,
    });

    // Add a delay between requests
    sleep(1);
}
