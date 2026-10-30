<?php

return [
    '--extends' => '--extends = file(local/forms/application/standard.jin)',
    'form' => [
        'name' => 'CPA',
        'fields' => [
            'person' => [
                'inStateCertificationNumber' => true,
                'inStateCertificationDate' => true,
                'outOfStateCertificationState' => null,
                'outOfStateCertificationNumber' => null,
                'interests' => true,
                'specialNeeds' => true,
            ],
        ],
    ],
];
