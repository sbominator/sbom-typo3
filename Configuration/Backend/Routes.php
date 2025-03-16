<?php

declare(strict_types=1);

use SBOMinator\SbomTYPO3\Controller\ModuleController;

return [
    'sbom_download' => [
        'path' => '/module/sbom/download',
        'target' => ModuleController::class . '::downloadAction',
    ]
];
