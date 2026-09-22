<?php

return [
    // Langues du site. La première est la langue par défaut.
    'locales' => ['fr', 'ar', 'en'],

    'locale_names' => [
        'fr' => 'Français',
        'ar' => 'العربية',
        'en' => 'English',
    ],

    'rtl_locales' => ['ar'],

    // Adresse qui reçoit les demandes de validation et une copie des messages des visiteurs.
    'admin_email' => env('TICDCE_ADMIN_EMAIL', 'contact@ticdce.tn'),

    'contact' => [
        'email' => env('TICDCE_CONTACT_EMAIL', env('TICDCE_ADMIN_EMAIL', 'contact@ticdce.tn')),
        'phone' => env('TICDCE_CONTACT_PHONE'),
        'address' => env('TICDCE_CONTACT_ADDRESS', 'Tunis, Tunisie'),
        'website' => env('TICDCE_WEBSITE'),
    ],
];
