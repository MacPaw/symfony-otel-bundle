/**
 * Smoke Test
 * Quick sanity check to verify all endpoints are working correctly
 * Runs with minimal load (1 VU) to catch basic errors
 */

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { BASE_URL, smokeOptions } from './config.js';

export const options = smokeOptions;

export default function () {
    group('Smoke Test - All Endpoints', function () {
        const endpoints = [
            { name: 'Homepage', url: '/', expectedStatus: 200 },
            { name: 'API Test', url: '/api/test', expectedStatus: 200 },
            { name: 'API Slow', url: '/api/slow', expectedStatus: 200 },
            { name: 'API Nested', url: '/api/nested', expectedStatus: 200 },
            { name: 'API PDO Test', url: '/api/pdo-test', expectedStatus: 200 },
            { name: 'API CQRS Test', url: '/api/cqrs-test', expectedStatus: 200 },
        ];

        endpoints.forEach(endpoint => {
            const response = http.get(`${BASE_URL}${endpoint.url}`);

            check(response, {
                [`${endpoint.name} - status is ${endpoint.expectedStatus}`]: (r) =>
                    r.status === endpoint.expectedStatus,
                [`${endpoint.name} - response time < 3s`]: (r) =>
                    r.timings.duration < 3000,
            });

            sleep(1);
        });
    });
}
