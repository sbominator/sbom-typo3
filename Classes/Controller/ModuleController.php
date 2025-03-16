<?php

declare(strict_types=1);

namespace SBOMinator\SbomTYPO3\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SBOMinator\Lib\Dependency;
use SBOMinator\Lib\Scanner\FileScanner;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

#[AsController]
final readonly class ModuleController
{
    public function __construct(
        private UriBuilder $uriBuilder,
        private IconFactory $iconFactory,
        private ModuleTemplateFactory $moduleTemplateFactory,
    )
    {
    }
    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $view = $this->moduleTemplateFactory->create($request);
        $buttonBar = $view->getDocHeaderComponent()->getButtonBar();
        $buttonBar->addButton(
            $buttonBar->makeLinkButton()
                ->setIcon($this->iconFactory->getIcon('actions-download', IconSize::SMALL))
                ->setTitle(LocalizationUtility::translate('LLL:EXT:sbom_typo3/Resources/Private/Language/Module.xlf:action.download', 'sbom_typo3'))
                ->setShowLabelText(true)
                ->setHref((string)$this->uriBuilder->buildUriFromRoute('sbom_download', []))
        );

        $view->assignMultiple([
            'flatDependencies' => $this->resolveDependencies(),
        ]);
        return $view->renderResponse('Module/Index');
    }

    public function downloadAction(ServerRequestInterface $request): ResponseInterface
    {
        // @todo this is not a real/valid SBOM output yet
        $json = [
            'packages' => array_map(
                static fn(Dependency $dependency): array => [
                    'name' => $dependency->getName(),
                    'versionInfo' => $dependency->getVersion(),
                    'downloadLocation' => null,
                    'licenseConcluded' => null,
                    'externalRefs' => [
                        'referenceCategory' => 'PACKAGE-MANAGER',
                        'referenceType' => 'purl',
                        'referenceLocator' => sprintf(
                            'pkg:composer:%s@%s',
                            $dependency->getName(),
                            $dependency->getVersion()
                        ),
                    ],
                ],
                $this->resolveDependencies(),
            )
        ];
        return (new JsonResponse($json))
            ->withHeader('Content-Disposition', 'attachment; filename=sbom.json');
    }

    /**
     * @return list<Dependency>
     */
    private function resolveDependencies(): array
    {
        $scanner = new FileScanner();
        $dependencies = $scanner->scanForDependencies(Environment::getProjectPath());
        return $this->flattenDependencies(...$dependencies);
    }

    /**
     * @return list<Dependency>
     */
    private function flattenDependencies(Dependency ...$dependencies): array
    {
        $items = [];
        foreach ($dependencies as $dependency) {
            $children = $dependency->getDependencies();
            if ($children === []) {
                $items[] = $dependency;
            } else {
                // clone dependency, skipping resolved children
                $items[] = new Dependency(
                    $dependency->getName(),
                    $dependency->getVersion(),
                    $dependency->getOrigin()
                );
                $items = array_merge($items, $this->flattenDependencies(...$children));
            }
        }
        return $items;
    }
}
