/**
 * CQRS Test
 * Tests the /api/cqrs-test endpoint
 * Verifies CQRS pattern with QueryBus and CommandBus tracing
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
    const response = http.get(`${BASE_URL}/api/cqrs-test`);

    check(response, {
        'status is 200': (r) => r.status === 200,
        'response time < 500ms': (r) => r.timings.duration < 500,
        'has operations': (r) => {
            try {
                const body = JSON.parse(r.body);
                return body.operations !== undefined &&
                       body.operations.query !== undefined &&
                       body.operations.command !== undefined;
            } catch (e) {
                return false;
            }
        },
        'has timestamp': (r) => {
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
