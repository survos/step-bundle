<?php

declare(strict_types=1);

/**
 * Default API providers registry for Survos StepBundle.
 */
return [
    'ip2location' => [
        'docs_url' => 'https://www.ip2location.io/dashboard',
        'note'     => 'Generate an API key on the IP2Location dashboard. Ensure your plan covers your endpoints.',
        'env'      => [
            'IP2LOCATION_API_KEY' => [
                'prompt' => 'Enter IP2LOCATION_API_KEY (hidden):',
                'hidden' => true,
            ],
        ],
    ],

    'bunny' => [
        'docs_url' => 'https://dash.bunny.net/account/api-key',
        'note'     => 'Create a Standard API Key in Bunny.net dashboard. Prefer read-only keys for CI where possible.',
        'env'      => [
            'BUNNY_API_KEY' => [
                'prompt' => 'Enter BUNNY_API_KEY (hidden):',
                'hidden' => true,
            ],
        ],
    ],

    'flickr' => [
        'docs_url' => 'https://www.flickr.com/services/api/keys/',
        'note'     => 'Create a key/secret on Flickr. Keep the secret safe for server-side apps.',
        'env'      => [
            'FLICKR_API_KEY' => [
                'prompt' => 'Enter FLICKR_API_KEY (hidden):',
                'hidden' => true,
            ],
            'FLICKR_API_SECRET' => [
                'prompt' => 'Enter FLICKR_API_SECRET (hidden):',
                'hidden' => true,
            ],
        ],
    ],
];
