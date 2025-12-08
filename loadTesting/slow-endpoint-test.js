/**
 * Slow Endpoint Test
 * Tests the /api/slow endpoint which includes a 2-second sleep
 * Verifies span tracking for long-running operations
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL } from './config.js';

export const options = {
    stages: [
        { duration: '30s', target: 5 },
        { duration: '1m', target: 10 },
        { duration: '30s', target: 0 },
    ],
    thresholds: {
        http_req_duration: ['p(95)<3000', 'p(99)<5000'],
        http_req_failed: ['rate<0.01'],
    },
};

export default function () {
    const response = http.get(`${BASE_URL}/api/slow`);

    check(response, {
        'status is 200': (r) => r.status === 200,
        'response time < 3s': (r) => r.timings.duration < 3000,
        'response time > 2s': (r) => r.timings.duration >= 2000,
        'has trace_id': (r) => {
            try {
                const body = JSON.parse(r.body);
                return body.trace_id !== undefined;
            } catch (e) {
                return false;
            }
        },
        'has duration field': (r) => {
            try {
                const body = JSON.parse(r.body);
                return body.duration === '2 seconds';
            } catch (e) {
                return false;
            }
        },
    });

    sleep(2);
}
