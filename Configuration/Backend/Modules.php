<?php

declare(strict_types=1);

use SBOMinator\SbomTYPO3\Controller\ModuleController;

return [
    'sbom_module' => [
        'parent' => 'system',
        'access' => 'admin',
        'path' => '/module/system/sbom',
        'icon' => 'EXT:sbom_typo3/Resources/Public/Icons/Extension.svg',
        'labels' => 'LLL:EXT:sbom_typo3/Resources/Private/Language/Module.xlf',
        'routes' => [
            '_default' => [
                'target' => ModuleController::class . '::indexAction',
            ],
        ],
    ],
];
