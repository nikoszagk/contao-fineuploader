<?php

declare(strict_types=1);

/*
 * FineUploader Bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2020, terminal42 gmbh
 * @author     terminal42 <https://terminal42.ch>
 * @license    MIT
 */

namespace Terminal42\FineUploaderBundle\EventListener;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\DataContainer;
use Monolog\Logger;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Terminal42\FineUploaderBundle\AssetsManager;
use Terminal42\FineUploaderBundle\RequestHandler\BackendHandler;

class BackendListener
{
    /**
     * @var AssetsManager
     */
    private $assetsManager;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var BackendHandler
     */
    private $requestHandler;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * BackendListener constructor.
     */
    public function __construct(
        AssetsManager $assetsManager,
        Logger $logger,
        BackendHandler $requestHandler,
        RequestStack $requestStack
    ) {
        $this->assetsManager = $assetsManager;
        $this->logger = $logger;
        $this->requestHandler = $requestHandler;
        $this->requestStack = $requestStack;
    }

    /**
     * Load the widget assets if they are needed. Load them here so the widget in subpalette can work as well.
     *
     * @param string $table
     */
    public function onLoadDataContainer($table): void
    {
        $request = $this->requestStack->getCurrentRequest();

        // Return if the scope is not backend or the DCA has no fields
        if (null === $request
            || ContaoCoreBundle::SCOPE_BACKEND !== $request->attributes->get('_scope')
            || !\is_array($GLOBALS['TL_DCA'][$table]['fields'])
        ) {
            return;
        }

        foreach ($GLOBALS['TL_DCA'][$table]['fields'] as $field) {
            if (isset($field['inputType']) && 'fineUploader' === $field['inputType']) {
                $this->assetsManager->includeAssets(
                    array_merge($this->assetsManager->getBasicAssets(), $this->assetsManager->getBackendAssets())
                );
                break;
            }
        }
    }

    /**
     * Dispatch an AJAX request in the backend.
     *
     * @param string $action
     *
     * @throws ResponseException
     */
    public function onExecutePostActions($action, DataContainer $dc): void
    {
        try {
            $response = $this->dispatchAction($action, $dc);
        } catch (\Exception $e) {
            $this->logger->log(
                LogLevel::ERROR,
                $e->getMessage(),
                ['contao' => new ContaoContext(($e->getTrace())[1]['function'], TL_ERROR)]
            );

            $response = new Response('Bad Request', 400);
        }

        if (null !== $response) {
            throw new ResponseException($response);
        }
    }

    /**
     * Dispatch the action.
     *
     * @param string $action
     *
     * @return Response|null
     */
    private function dispatchAction($action, DataContainer $dc)
    {
        if ('fineuploader_upload' === $action) {
            return $this->requestHandler->handleUploadRequest($this->requestStack->getCurrentRequest(), $dc);
        }

        if ('fineuploader_reload' === $action) {
            return $this->requestHandler->handleReloadRequest($this->requestStack->getCurrentRequest(), $dc);
        }

        return null;
    }
}
