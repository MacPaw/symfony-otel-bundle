/**
 * All Scenarios Test
 * Comprehensive load test that runs all test scenarios in parallel
 * Uses k6 scenarios feature for advanced execution control
 *
 * Duration: ~15 minutes
 *
 * This test simulates a realistic production environment by running
 * multiple test types concurrently with staggered start times.
 */

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { BASE_URL } from './config.js';

// Configure all scenarios to run in parallel with staggered starts
export const options = {
    scenarios: {
        // Scenario 1: Smoke test - Quick validation
        smoke_test: {
            executor: 'constant-vus',
            exec: 'smokeTest',
            vus: 1,
            duration: '1m',
            tags: { scenario: 'smoke' },
            startTime: '0s',
        },

        // Scenario 2: Basic load test
        basic_load: {
            executor: 'ramping-vus',
            exec: 'basicTest',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 10 },
                { duration: '1m', target: 20 },
                { duration: '30s', target: 50 },
                { duration: '1m', target: 20 },
                { duration: '30s', target: 0 },
            ],
            tags: { scenario: 'basic_load' },
            startTime: '1m',
        },

        // Scenario 3: Nested spans test
        nested_spans: {
            executor: 'constant-vus',
            exec: 'nestedSpansTest',
            vus: 10,
            duration: '2m',
            tags: { scenario: 'nested_spans' },
            startTime: '4m30s',
        },

        // Scenario 4: PDO test
        pdo_test: {
            executor: 'constant-vus',
            exec: 'pdoTest',
            vus: 10,
            duration: '2m',
            tags: { scenario: 'pdo' },
            startTime: '6m30s',
        },

        // Scenario 5: CQRS test
        cqrs_test: {
            executor: 'constant-vus',
            exec: 'cqrsTest',
            vus: 10,
            duration: '2m',
            tags: { scenario: 'cqrs' },
            startTime: '8m30s',
        },

        // Scenario 6: Slow endpoint test
        slow_endpoint: {
            executor: 'ramping-vus',
            exec: 'slowEndpointTest',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 5 },
                { duration: '1m', target: 10 },
                { duration: '30s', target: 0 },
            ],
            tags: { scenario: 'slow_endpoint' },
            startTime: '10m30s',
        },

        // Scenario 7: Comprehensive mixed workload
        comprehensive: {
            executor: 'ramping-vus',
            exec: 'comprehensiveTest',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 10 },
                { duration: '1m', target: 20 },
                { duration: '30s', target: 50 },
                { duration: '1m', target: 20 },
                { duration: '30s', target: 0 },
            ],
            tags: { scenario: 'comprehensive' },
            startTime: '12m30s',
        },
    },

    // Global thresholds with scenario-specific tags
    thresholds: {
        'http_req_duration{scenario:smoke}': ['p(95)<3000'],
        'http_req_duration{scenario:basic_load}': ['p(95)<500', 'p(99)<1000'],
        'http_req_duration{scenario:nested_spans}': ['p(95)<2000', 'p(99)<3000'],
        'http_req_duration{scenario:pdo}': ['p(95)<500', 'p(99)<1000'],
        'http_req_duration{scenario:cqrs}': ['p(95)<500', 'p(99)<1000'],
        'http_req_duration{scenario:slow_endpoint}': ['p(95)<3000', 'p(99)<5000'],
        'http_req_duration{scenario:comprehensive}': ['p(95)<2000', 'p(99)<3000'],
        'http_req_failed': ['rate<0.01'], // Global failure rate < 1%
    },
};

// Smoke Test Function
export function smokeTest() {
    group('Smoke Test - All Endpoints', function () {
        const endpoints = [
            { name: 'Homepage', url: '/' },
            { name: 'API Test', url: '/api/test' },
            { name: 'API Slow', url: '/api/slow' },
            { name: 'API Nested', url: '/api/nested' },
            { name: 'API PDO Test', url: '/api/pdo-test' },
            { name: 'API CQRS Test', url: '/api/cqrs-test' },
        ];

        endpoints.forEach(endpoint => {
            const response = http.get(`${BASE_URL}${endpoint.url}`);
            check(response, {
                [`${endpoint.name} - status is 200`]: (r) => r.status === 200,
                [`${endpoint.name} - response time < 3s`]: (r) => r.timings.duration < 3000,
            });
            sleep(1);
        });
    });
}

// Basic Test Function
export function basicTest() {
    group('Basic Load Test', function () {
        const response = http.get(`${BASE_URL}/api/test`);

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
        });

        sleep(1);
    });
}

// Nested Spans Test Function
export function nestedSpansTest() {
    group('Nested Spans Test', function () {
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
        });

        sleep(1);
    });
}

// PDO Test Function
export function pdoTest() {
    group('PDO Test', function () {
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
        });

        sleep(1);
    });
}

// CQRS Test Function
export function cqrsTest() {
    group('CQRS Test', function () {
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
        });

        sleep(1);
    });
}

// Slow Endpoint Test Function
export function slowEndpointTest() {
    group('Slow Endpoint Test', function () {
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
        });

        sleep(2);
    });
}

// Comprehensive Test Function
export function comprehensiveTest() {
    group('Comprehensive Mixed Workload', function () {
        // Weighted endpoint distribution
        const endpoints = [
            { url: '/api/test', weight: 40 },
            { url: '/api/nested', weight: 25 },
            { url: '/api/pdo-test', weight: 20 },
            { url: '/api/cqrs-test', weight: 10 },
            { url: '/api/slow', weight: 5 },
        ];

        // Select endpoint based on weighted distribution
        const random = Math.random() * 100;
        let cumulativeWeight = 0;
        let selectedEndpoint = endpoints[0].url;

        for (const endpoint of endpoints) {
            cumulativeWeight += endpoint.weight;
            if (random <= cumulativeWeight) {
                selectedEndpoint = endpoint.url;
                break;
            }
        }

        const response = http.get(`${BASE_URL}${selectedEndpoint}`);

        check(response, {
            'status is 200': (r) => r.status === 200,
            'response time acceptable': (r) => {
                if (selectedEndpoint === '/api/slow') {
                    return r.timings.duration < 3000;
                } else if (selectedEndpoint === '/api/nested') {
                    return r.timings.duration < 2000;
                }
                return r.timings.duration < 1000;
            },
        });

        // Variable sleep based on endpoint
        if (selectedEndpoint === '/api/slow') {
            sleep(2);
        } else {
            sleep(Math.random() * 2 + 1);
        }
    });
}
