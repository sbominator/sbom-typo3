<?php

declare(strict_types=1);

namespace SBOMinator\SbomTYPO3\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SBOMinator\Lib\Dependency;
use SBOMinator\Lib\Enum\FileType;
use SBOMinator\Lib\Generator\CycloneDXSBOMGenerator;
use SBOMinator\Lib\Generator\SpdxSBOMGenerator;
use SBOMinator\Lib\Scanner\FileScanner;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\Response;
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
                ->setTitle(LocalizationUtility::translate('LLL:EXT:sbom_typo3/Resources/Private/Language/Module.xlf:action.download.SPDX', 'sbom_typo3'))
                ->setShowLabelText(true)
                ->setHref((string)$this->uriBuilder->buildUriFromRoute('sbom_download', [
                    'format' => FileType::SPDX_SBOM_FILE->value,
                ])),
            ButtonBar::BUTTON_POSITION_LEFT,
            1
        );

        $buttonBar->addButton(
            $buttonBar->makeLinkButton()
                ->setIcon($this->iconFactory->getIcon('actions-download', IconSize::SMALL))
                ->setTitle(LocalizationUtility::translate('LLL:EXT:sbom_typo3/Resources/Private/Language/Module.xlf:action.download.cycloneDX', 'sbom_typo3'))
                ->setShowLabelText(true)
                ->setHref((string)$this->uriBuilder->buildUriFromRoute('sbom_download', [
                    'format' => FileType::CYCLONEDX_SBOM_FILE->value,
                ])),
            ButtonBar::BUTTON_POSITION_LEFT,
            2
        );

        $view->assignMultiple([
            'flatDependencies' => $this->flattenDependencies(...$this->resolveDependencies()),
        ]);
        return $view->renderResponse('Module/Index');
    }

    public function downloadAction(ServerRequestInterface $request): ResponseInterface
    {
        $format = $request->getQueryParams()['format'] ?? '';
        $format = is_string($format) ? FileType::tryFrom($format) : null;
        $format ??= FileType::SPDX_SBOM_FILE;

        $dependencies = $this->resolveDependencies();
        if ($format === FileType::CYCLONEDX_SBOM_FILE) {
            $generator = new CycloneDXSBOMGenerator($dependencies);
        } else {
            $generator = new SpdxSBOMGenerator($dependencies);
        }
        $response = new Response();
        $response->getBody()->write($generator->generate());

        // @todo use stream-emitting-response
        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename=sbom.json');
    }

    /**
     * @return list<Dependency>
     */
    private function resolveDependencies(): array
    {
        return (new FileScanner())->scanForDependencies(Environment::getProjectPath());
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
