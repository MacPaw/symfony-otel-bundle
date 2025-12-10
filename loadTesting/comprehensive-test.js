/**
 * Comprehensive Test
 * Mixed workload test hitting all endpoints with weighted distribution
 * Simulates realistic production traffic patterns
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL } from './config.js';

export const options = {
    stages: [
        { duration: '30s', target: 10 },
        { duration: '1m', target: 20 },
        { duration: '30s', target: 50 },
        { duration: '1m', target: 20 },
        { duration: '30s', target: 0 },
    ],
    thresholds: {
        http_req_duration: ['p(95)<2000', 'p(99)<3000'],
        http_req_failed: ['rate<0.01'],
    },
};

// Weighted endpoint distribution (must sum to 100)
const endpoints = [
    { url: '/api/test', weight: 40 },          // 40% - Most common, fast endpoint
    { url: '/api/nested', weight: 25 },        // 25% - Complex operation
    { url: '/api/pdo-test', weight: 20 },      // 20% - Database operation
    { url: '/api/cqrs-test', weight: 10 },     // 10% - CQRS pattern
    { url: '/api/slow', weight: 5 },           // 5% - Slow operation
];

export default function () {
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
            // Different thresholds for different endpoints
            if (selectedEndpoint === '/api/slow') {
                return r.timings.duration < 3000;
            } else if (selectedEndpoint === '/api/nested') {
                return r.timings.duration < 2000;
            }
            return r.timings.duration < 1000;
        },
        'valid JSON response': (r) => {
            try {
                JSON.parse(r.body);
                return true;
            } catch (e) {
                return false;
            }
        },
    });

    // Variable sleep time based on endpoint
    if (selectedEndpoint === '/api/slow') {
        sleep(2);
    } else {
        sleep(Math.random() * 2 + 1);
    }
}
