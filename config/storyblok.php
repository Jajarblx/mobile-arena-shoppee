<?php

return [
    'access_token' => env('STORYBLOK_ACCESS_TOKEN'),
    'version' => env('STORYBLOK_VERSION', 'draft'),
    'cache_ttl' => (int) env('STORYBLOK_CACHE_TTL', 300),
];
