<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Beuser\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Beuser\Domain\Model\BackendUserGroup;
use TYPO3\CMS\Beuser\Domain\Repository\BackendUserGroupRepository;
use TYPO3\CMS\Beuser\Service\UserInformationService;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * Backend module user group administration controller
 * @internal This class is a TYPO3 Backend implementation and is not considered part of the Public TYPO3 API.
 */
class BackendUserGroupController extends ActionController
{
    /**
     * @var \TYPO3\CMS\Beuser\Domain\Repository\BackendUserGroupRepository
     */
    protected $backendUserGroupRepository;

    /**
     * @param \TYPO3\CMS\Beuser\Domain\Repository\BackendUserGroupRepository $backendUserGroupRepository
     */
    public function injectBackendUserGroupRepository(BackendUserGroupRepository $backendUserGroupRepository)
    {
        $this->backendUserGroupRepository = $backendUserGroupRepository;
    }

    /**
     * @var UserInformationService
     */
    protected $userInformationService;

    public function __construct(
        UserInformationService $userInformationService
    ) {
        $this->userInformationService = $userInformationService;
    }

    /**
     * Displays all BackendUserGroups
     * @param int $currentPage
     */
    public function indexAction(int $currentPage = 1): ResponseInterface
    {
        /** @var QueryResultInterface<int, BackendUserGroup> $backendUsers */
        $backendUsers = $this->backendUserGroupRepository->findAll();
        $paginator = new QueryResultPaginator($backendUsers, $currentPage, 50);
        $pagination = new SimplePagination($paginator);
        $compareGroupUidList = array_keys($this->getBackendUser()->uc['beuser']['compareGroupUidList'] ?? []);
        $this->view->assignMultiple(
            [
                'shortcutLabel' => 'backendUserGroupsMenu',
                'paginator' => $paginator,
                'pagination' => $pagination,
                'totalAmountOfBackendUserGroups' => $backendUsers->count(),
                'route' => $this->getRequest()->getAttribute('route')->getPath(),
                'action' => $this->request->getControllerActionName(),
                'controller' => $this->request->getControllerName(),
                'compareGroupUidList' => array_map(static function ($value) { // uid as key and force value to 1
                    return 1;
                }, array_flip($compareGroupUidList)),
                'compareGroupList' => !empty($compareGroupUidList) ? $this->backendUserGroupRepository->findByUidList($compareGroupUidList) : [],
            ]
        );

        return $this->htmlResponse($this->view->render());
    }

    public function compareAction(): ResponseInterface
    {
        $compareGroupUidList = array_keys($this->getBackendUser()->uc['beuser']['compareGroupUidList'] ?? []);

        $compareData = [];
        foreach ($compareGroupUidList as $uid) {
            if ($compareInformation = $this->userInformationService->getGroupInformation($uid)) {
                $compareData[] = $compareInformation;
            }
        }
        if (empty($compareData)) {
            $this->redirect('index');
        }

        $this->view->assignMultiple([
            'shortcutLabel' => 'compareBackendUsersGroups',
            'route' => $this->getRequest()->getAttribute('route')->getPath(),
            'action' => $this->request->getControllerActionName(),
            'controller' => $this->request->getControllerName(),
            'compareGroupList' => $compareData,
        ]);

        return $this->htmlResponse($this->view->render());
    }

    /**
     * Attaches one backend user group to the compare list
     *
     * @param int $uid
     */
    public function addToCompareListAction(int $uid): void
    {
        $list = $this->getBackendUser()->uc['beuser']['compareGroupUidList'] ?? [];
        $list[$uid] = true;
        $this->getBackendUser()->uc['beuser']['compareGroupUidList'] = $list;
        $this->getBackendUser()->writeUC();

        $this->redirect('index');
    }

    /**
     * Removes given backend user group to the compare list
     *
     * @param int $uid
     * @param int $redirectToCompare
     */
    public function removeFromCompareListAction(int $uid, int $redirectToCompare = 0): void
    {
        $list = $this->getBackendUser()->uc['beuser']['compareGroupUidList'] ?? [];
        unset($list[$uid]);
        $this->getBackendUser()->uc['beuser']['compareGroupUidList'] = $list;
        $this->getBackendUser()->writeUC();

        if ($redirectToCompare) {
            $this->redirect('compare');
        } else {
            $this->redirect('index');
        }
    }

    /**
     * Removes all backend user groups from the compare list
     */
    public function removeAllFromCompareListAction(): void
    {
        $this->getBackendUser()->uc['beuser']['compareGroupUidList'] = [];
        $this->getBackendUser()->writeUC();
        $this->redirect('index');
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @return ServerRequestInterface
     */
    protected function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
