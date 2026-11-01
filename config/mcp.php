<?php

return [

    // Prefixes (scheme and host) that dynamically registered OAuth clients may
    // use as redirect URIs. "*" allows any; the consent screen still requires
    // the user to approve every client.
    'redirect_domains' => array_values(array_filter(array_map(
        static fn (string $domain): string => trim($domain),
        explode(',', (string) env('MCP_REDIRECT_DOMAINS', '*')),
    ))),

    // Private-use URI schemes (RFC 8252) that native desktop clients redirect to.
    'custom_schemes' => [
        'claude',
        'cursor',
        'vscode',
    ],

    'authorization_server' => null,

];
