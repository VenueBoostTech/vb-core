
<?php

return [
    'default' => env('FIREBASE_PROJECT', 'electral-staff-app'),

    'projects' => [
        'electral-staff-app' => [
            'credentials' => [
                'file' => storage_path('app/electral-staff-app-firebase-adminsdk-6el8r-8eeaba22d2.json'),
            ],
            'project_id' => env('FIREBASE_PROJECT_ID'),
        ],
        'staffluent-app' => [
            'credentials' => [
                'file' => storage_path('app/staffluent-app-firebase-adminsdk-90jru-9b9034cd03.json'),
            ],
            'project_id' => env('FIREBASE_PROJECT_ID_STAFFLUENT'),
        ],
    ],
];


// return [
//     'credentials' => [
//         'file' => storage_path('electral-staff-app-firebase-adminsdk-6el8r-8eeaba22d2'),
//     ],
//     'project_id' => env('FIREBASE_PROJECT_ID'),
// ];
