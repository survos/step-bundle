<?php

declare(strict_types=1);

/**
 * Default API providers registry for Survos StepBundle.
 *
 * You can extend/override these at runtime by passing $override to ApiKeyUtil::ensureFor(),
 * or by decorating ApiKeyUtil::getRegistry() if you wire this in a container.
 */
return [
    // ip2location-bundle
    'ip2location' => [
        'docs_url' => 'https://www.ip2location.io/dashboard',
        'note'     => 'Generate an API key on the IP2Location dashboard. Ensure your plan allows the endpoints you intend to call.',
        'env'      => [
            'IP2LOCATION_API_KEY' => [
                'prompt' => 'Enter IP2LOCATION_API_KEY (hidden):',
                'hidden' => true,
            ],
        ],
    ],

    // bunny-bundle
    'bunny' => [
        'docs_url' => 'https://dash.bunny.net/account/api-key',
        'note'     => 'Create a Standard API Key in Bunny.net dashboard. Consider read-only keys for CI.',
        'env'      => [
            'BUNNY_API_KEY' => [
                'prompt' => 'Enter BUNNY_API_KEY (hidden):',
                'hidden' => true,
            ],
        ],
    ],

    // flickr-bundle
    'flickr' => [
        'docs_url' => 'https://www.flickr.com/services/api/keys/',
//        'note'     => 'Create a key/secret on Flickr. For server-side apps, keep the secret safe.',
        'env'      => [
            'FLICKR_API_KEY'    => [
                'prompt' => 'Enter FLICKR_API_KEY (hidden):',
                'hidden' => true,
            ],
            'FLICKR_API_SECRET' => [
                'prompt' => 'Enter FLICKR_API_SECRET (hidden):',
                'hidden' => true,
            ],
        ],
    ],

    'openai' => [
        'docs_url' => 'https://platform.openai.com/api-keys',
        'env' => [
            'OPENAI_API_KEY' => ['prompt' => 'Enter OPENAI_API_KEY (hidden):', 'hidden' => true],
        ],
    ],

    'meili' => [
        'docs_url' => 'https://www.meilisearch.com/docs/learn/security/master_api_keys',
        // 'docs_url' => 'https://127.0.0.1:7700', // @todo: inject the server! So do this in a separate step
        'env' => [
            'MEILISEARCH_URL' => ['prompt' => 'Enter MEILISEARCH_URL (e.g. http://127.0.0.1:7700):', 'hidden' => false],
            'MEILI_API_KEY' => ['prompt' => 'Enter MEILISEARCH_API_KEY (hidden):', 'hidden' => true],
            'MEILI_SEARCH_KEY' => ['prompt' => 'public readonly search key:', 'hidden' => true],
            // if you also want to ensure URL here, uncomment:
        ],
    ],

];
