/**
 * Comparison Load Test
 * Tests both the OTel-enabled app and baseline app to compare performance
 * Helps measure the overhead of OpenTelemetry instrumentation
 */

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { BASE_URL, BASE_URL_BASELINE } from './config.js';
import { Trend } from 'k6/metrics';

// Custom metrics for comparison
const otelAppDuration = new Trend('otel_app_duration', true);
const baselineAppDuration = new Trend('baseline_app_duration', true);

export const options = {
    stages: [
        { duration: '30s', target: 10 },
        { duration: '1m', target: 20 },
        { duration: '30s', target: 50 },
        { duration: '1m', target: 20 },
        { duration: '30s', target: 0 },
    ],
    thresholds: {
        http_req_duration: ['p(95)<1000', 'p(99)<2000'],
        http_req_failed: ['rate<0.01'],
        'otel_app_duration': ['p(95)<1000'],
        'baseline_app_duration': ['p(95)<1000'],
    },
};

export default function () {
    // Test OTel-enabled app
    group('OTel App', () => {
        const response = http.get(`${BASE_URL}/api/test`);

        check(response, {
            'OTel: status is 200': (r) => r.status === 200,
            'OTel: response time < 500ms': (r) => r.timings.duration < 500,
        });

        otelAppDuration.add(response.timings.duration);
    });

    // Test baseline app (without OTel)
    group('Baseline App', () => {
        const response = http.get(`${BASE_URL_BASELINE}/api/test`);

        check(response, {
            'Baseline: status is 200': (r) => r.status === 200,
            'Baseline: response time < 500ms': (r) => r.timings.duration < 500,
        });

        baselineAppDuration.add(response.timings.duration);
    });

    sleep(1);
}

export function handleSummary(data) {
    const otelP95 = data.metrics.otel_app_duration?.values['p(95)'] || 0;
    const baselineP95 = data.metrics.baseline_app_duration?.values['p(95)'] || 0;
    const overhead = baselineP95 > 0 ? ((otelP95 - baselineP95) / baselineP95 * 100).toFixed(2) : 0;

    console.log('\n=== Performance Comparison ===');
    console.log(`OTel App p95: ${otelP95.toFixed(2)}ms`);
    console.log(`Baseline App p95: ${baselineP95.toFixed(2)}ms`);
    console.log(`Overhead: ${overhead}%`);
    console.log('=============================\n');

    return {
        'stdout': JSON.stringify(data, null, 2),
    };
}