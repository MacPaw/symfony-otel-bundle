/**
 * Stress Test
 * Pushes the system beyond normal operating capacity
 * Helps identify breaking points and performance degradation
 * WARNING: Takes approximately 31 minutes to complete
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL, stressOptions } from './config.js';

export const options = stressOptions;

const endpoints = [
    '/api/test',
    '/api/nested',
    '/api/pdo-test',
    '/api/cqrs-test',
];

export default function () {
    // Random endpoint selection
    const endpoint = endpoints[Math.floor(Math.random() * endpoints.length)];
    const response = http.get(`${BASE_URL}${endpoint}`);

    check(response, {
        'status is 200': (r) => r.status === 200,
        'response time < 5s': (r) => r.timings.duration < 5000,
    });

    sleep(0.5);
}
