<?php

declare(strict_types=1);

/**
 * Flextype (https://flextype.org)
 * Founded by Sergey Romanenko and maintained by Flextype Community.
 */

namespace Flextype;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function array_replace_recursive;
use function filesystem;
use function flextype;

/**
 * Clear cache
 *
 * endpoint: POST /api/utils/cache/clear
 *
 * Parameters:
 * path - [REQUIRED] - The file path with valid params for image manipulations.
 *
 * Query:
 * token  - [REQUIRED] - Valid token.
 *
 * Returns:
 * Image file
 */
app()->post('/api/utils/cache/clear', function (Request $request, Response $response) {
    
    // Get Query Params
    $data = $request->getParsedBody();

    // Check is images api enabled
    if (! registry()->get('flextype.settings.api.utils.enabled')) {
        return getApiResponseWithError($response, 400);
    }

    // Check is token param exists
    if (! isset($data['token'])) {
        return getApiResponseWithError($response, 400);
    }

    // Check is token exists
    if (! tokens()->has($data['token'])) {
        return getApiResponseWithError($response, 401);
    }

    // Fetch token
    $tokenData = tokens()->fetch($data['token']);

    // Verify access token
    if (password_verify($tokenData['hashed_access_token'], $data['access_token'])) {
        return getApiResponseWithError($response, 401);
    }

    // Check token state and limit_calls
    if ($tokenData['state'] === 'disabled' || 
        ($tokenData['limit_calls'] !== 0 && $tokenData['calls'] >= $tokenData['limit_calls'])) {
        return getApiResponseWithError($response, 400);
    }

    // Update token calls
    tokens()->update($data['token'], ['calls' => $data['calls'] + 1]);

    // Clear cache
    filesystem()->directory(PATH['tmp'])->delete();

    // Return success response
    return ;
});