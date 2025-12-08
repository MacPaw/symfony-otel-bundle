/**
 * PDO Test
 * Tests the /api/pdo-test endpoint
 * Verifies PDO instrumentation (ExampleHookInstrumentation)
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL } from './config.js';

export const options = {
    vus: 10,
    duration: '2m',
    thresholds: {
        http_req_duration: ['p(95)<500', 'p(99)<1000'],
        http_req_failed: ['rate<0.01'],
    },
};

export default function () {
    const response = http.get(`${BASE_URL}/api/pdo-test`);

    check(response, {
        'status is 200': (r) => r.status === 200,
        'response time < 500ms': (r) => r.timings.duration < 500,
        'has pdo_result': (r) => {
            try {
                const body = JSON.parse(r.body);
                return body.pdo_result !== undefined;
            } catch (e) {
                return false;
            }
        },
        'pdo_result has test_value': (r) => {
            try {
                const body = JSON.parse(r.body);
                return body.pdo_result.test_value !== undefined;
            } catch (e) {
                return false;
            }
        },
        'pdo_result has message': (r) => {
            try {
                const body = JSON.parse(r.body);
                return body.pdo_result.message !== undefined;
            } catch (e) {
                return false;
            }
        },
    });

    sleep(1);
}
