/**
 * Nested Spans Test
 * Tests the /api/nested endpoint with multiple nested spans
 * Verifies complex span hierarchies are properly traced
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL } from './config.js';

export const options = {
    vus: 10,
    duration: '2m',
    thresholds: {
        http_req_duration: ['p(95)<2000', 'p(99)<3000'],
        http_req_failed: ['rate<0.01'],
    },
};

export default function () {
    const response = http.get(`${BASE_URL}/api/nested`);

    check(response, {
        'status is 200': (r) => r.status === 200,
        'response time < 2s': (r) => r.timings.duration < 2000,
        'has operations array': (r) => {
            try {
                const body = JSON.parse(r.body);
                return Array.isArray(body.operations) && body.operations.length === 2;
            } catch (e) {
                return false;
            }
        },
        'has trace_id': (r) => {
            try {
                const body = JSON.parse(r.body);
                return body.trace_id !== undefined;
            } catch (e) {
                return false;
            }
        },
        'includes database operation': (r) => {
            try {
                const body = JSON.parse(r.body);
                return body.operations.includes('database_query');
            } catch (e) {
                return false;
            }
        },
        'includes external API operation': (r) => {
            try {
                const body = JSON.parse(r.body);
                return body.operations.includes('external_api_call');
            } catch (e) {
                return false;
            }
        },
    });

    sleep(1);
}
