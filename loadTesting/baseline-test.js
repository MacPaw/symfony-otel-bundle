/**
 * Baseline App Load Test
 * Tests the /api/test endpoint on the baseline app (without OpenTelemetry)
 * Same test as basic-test.js but for the baseline app
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL_BASELINE } from './config.js';

export const options = {
    stages: [
        { duration: '30s', target: 10 },
        { duration: '1m', target: 20 },
        { duration: '30s', target: 50 },
        { duration: '1m', target: 20 },
        { duration: '30s', target: 0 },
    ],
    thresholds: {
        http_req_duration: ['p(95)<500', 'p(99)<1000'],
        http_req_failed: ['rate<0.01'],
    },
};

export default function () {
    const response = http.get(`${BASE_URL_BASELINE}/api/test`);

    check(response, {
        'status is 200': (r) => r.status === 200,
        'response time < 500ms': (r) => r.timings.duration < 500,
        'has message field': (r) => {
            try {
                const body = JSON.parse(r.body);
                return body.message !== undefined;
            } catch (e) {
                return false;
            }
        },
        'has timestamp field': (r) => {
            try {
                const body = JSON.parse(r.body);
                return body.timestamp !== undefined;
            } catch (e) {
                return false;
            }
        },
    });

    sleep(1);
}
