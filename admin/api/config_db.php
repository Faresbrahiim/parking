<?php

/**

 * XAMPP par défaut : utilisateur root, mot de passe vide.

 * Modifiez si besoin (ou variables d'environnement PFA_DB_*).

 */

return [

    'host' => getenv('PFA_DB_HOST') ?: '127.0.0.1',

    'user' => getenv('PFA_DB_USER') ?: 'root',

    'pass' => getenv('PFA_DB_PASS') === false ? '' : (string) getenv('PFA_DB_PASS'),

    'name' => getenv('PFA_DB_NAME') ?: 'parking_db',

];

