<?php

use function Pest\Laravel\get;

test('health endpoint returns healthy status', function () {
    get('/api/v1/health')
        ->assertOk()
        ->assertJson([
            'status' => 'healthy',
        ]);
});

test('unauthenticated requests return 401', function () {
    get('/api/v1/users')
        ->assertUnauthorized();
});
