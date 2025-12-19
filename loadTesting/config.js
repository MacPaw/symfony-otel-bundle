// k6 Load Testing Configuration
// Base URL from environment or default
export const BASE_URL = __ENV.BASE_URL || 'http://php-app:8080';
export const BASE_URL_BASELINE = __ENV.BASE_URL_BASELINE || 'http://php-app-baseline:8080';

// Common thresholds for all tests
export const thresholds = {
    http_req_duration: ['p(95)<2000', 'p(99)<3000'],
    http_req_failed: ['rate<0.01'], // Less than 1% failures
    http_reqs: ['rate>5'], // At least 5 requests per second
};

// Smoke test options - minimal load
export const smokeOptions = {
    vus: 1,
    duration: '1m',
    thresholds: {
        http_req_duration: ['p(95)<3000'],
        http_req_failed: ['rate<0.01'],
    },
};

// Load test options - sustained load
export const loadOptions = {
    stages: [
        { duration: '2m', target: 50 },  // Ramp up to 50 users
        { duration: '5m', target: 50 },  // Stay at 50 users
        { duration: '2m', target: 0 },   // Ramp down
    ],
    thresholds: thresholds,
};

// Stress test options - finding breaking point
export const stressOptions = {
    stages: [
        { duration: '2m', target: 100 },
        { duration: '5m', target: 100 },
        { duration: '2m', target: 200 },
        { duration: '5m', target: 200 },
        { duration: '2m', target: 300 },
        { duration: '5m', target: 300 },
        { duration: '10m', target: 0 },
    ],
    thresholds: {
        http_req_duration: ['p(95)<3000', 'p(99)<5000'],
        http_req_failed: ['rate<0.05'],
    },
};

// Spike test options - sudden load increase
export const spikeOptions = {
    stages: [
        { duration: '10s', target: 100 },
        { duration: '1m', target: 100 },
        { duration: '10s', target: 1400 }, // Spike!
        { duration: '3m', target: 1400 },
        { duration: '10s', target: 100 },
        { duration: '3m', target: 100 },
        { duration: '10s', target: 0 },
    ],
};
