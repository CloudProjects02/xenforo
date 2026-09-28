<?php

namespace Jace\TokenDownloads\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Package extends AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('tokenDownloads');
    }

    public function actionIndex()
    {
        $packageRepo = $this->getPackageRepo();
        $packages = $packageRepo->findPackagesForList()->fetch();

        $viewParams = [
            'packages' => $packages
        ];
        return $this->view('Jace\TokenDownloads:Package\Listing', 'jace_token_downloads_package_list', $viewParams);
    }

    public function actionAdd()
    {
        $package = $this->em()->create('Jace\TokenDownloads:Package');

        return $this->packageAddEdit($package);
    }

    public function actionEdit(ParameterBag $params)
    {
        $package = $this->assertPackageExists($params->package_id);
        return $this->packageAddEdit($package);
    }

    protected function packageAddEdit(\Jace\TokenDownloads\Entity\Package $package)
    {
        $paymentRepo = $this->repository('XF:Payment');
        $paymentProfiles = $paymentRepo->findPaymentProfilesForList()->fetch();

        $viewParams = [
            'package' => $package,
            'paymentProfiles' => $paymentProfiles
        ];
        return $this->view('Jace\TokenDownloads:Package\Edit', 'jace_token_downloads_package_edit', $viewParams);
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params->package_id)
        {
            $package = $this->assertPackageExists($params->package_id);
        }
        else
        {
            $package = $this->em()->create('Jace\TokenDownloads:Package');
        }

        $this->packageSaveProcess($package)->run();

        return $this->redirect($this->buildLink('token-downloads/packages'));
    }

    protected function packageSaveProcess(\Jace\TokenDownloads\Entity\Package $package)
    {
        $entityInput = $this->filter([
            'title' => 'str',
            'description' => 'str',
            'cost_amount' => 'float',
            'cost_currency' => 'str',
            'download_limit' => 'uint',
            'active' => 'bool',
            'display_order' => 'uint',
            'payment_profile_ids' => 'array-uint'
        ]);

        $form = $this->formAction();
        $form->basicEntitySave($package, $entityInput);

        // Handle icon upload
        $upload = $this->request->getFile('package_icon', false, false);
        if ($upload)
        {
            $packageIconService = $this->service('Jace\TokenDownloads:Package\PackageIcon', $package);
            
            if (!$packageIconService->setImageFromUpload($upload))
            {
                $form->logError($packageIconService->getError(), 'package_icon');
            }
            else
            {
                $form->apply(function() use ($packageIconService)
                {
                    $packageIconService->updatePackageIcon();
                });
            }
        }

        // Handle icon deletion
        if ($this->filter('delete_icon', 'bool'))
        {
            $form->apply(function() use ($package)
            {
                $packageIconService = $this->service('Jace\TokenDownloads:Package\PackageIcon', $package);
                $packageIconService->deletePackageIcon();
            });
        }

        return $form;
    }

    public function actionIconUpload(ParameterBag $params)
    {
        $this->assertPostOnly();

        $package = $this->assertPackageExists($params->package_id);

        $upload = $this->request->getFile('upload', false, false);
        if (!$upload)
        {
            return $this->error(\XF::phrase('no_file_uploaded'));
        }

        $packageIconService = $this->service('Jace\TokenDownloads:Package\PackageIcon', $package);
        
        if (!$packageIconService->setImageFromUpload($upload))
        {
            return $this->error($packageIconService->getError());
        }

        if (!$packageIconService->updatePackageIcon())
        {
            return $this->error(\XF::phrase('there_was_problem_uploading_your_file'));
        }

        $reply = $this->message(\XF::phrase('upload_completed_successfully'));
        $reply->setJsonParam('iconUrl', $package->getIconUrl());
        
        return $reply;
    }

    public function actionIconDelete(ParameterBag $params)
    {
        $this->assertPostOnly();

        $package = $this->assertPackageExists($params->package_id);

        if (!$package->package_icon_date)
        {
            return $this->error(\XF::phrase('no_icon_to_delete'));
        }

        $packageIconService = $this->service('Jace\TokenDownloads:Package\PackageIcon', $package);
        $packageIconService->deletePackageIcon();

        return $this->message(\XF::phrase('icon_deleted_successfully'));
    }

    public function actionGetPackageIcon(ParameterBag $params)
    {
        $this->assertPostOnly();

        $package = $this->assertPackageExists($params->package_id);

        if (!$package->getIconUrl())
        {
            return $this->error(\XF::phrase('no_icon_available'));
        }

        $description = "<img src=\"{$package->getIconUrl()}\" style=\"max-width:100px; max-height:75px;\" alt=\"{$package->title}\" />";

        $view = $this->view('');
        $view->setJsonParam('description', $description);

        return $view;
    }

    public function actionDelete(ParameterBag $params) {

        $package = $this->assertPackageExists($params->package_id);

        /** @var \XF\ControllerPlugin\Delete $plugin */
        $plugin = $this->plugin('XF:Delete');

        return $plugin->actionDelete(
                        $package,
                        $this->buildLink('token-downloads/packages/delete', $package),
                        $this->buildLink('token-downloads/packages/edit', $package),
                        $this->buildLink('token-downloads'),
                        $package->title
        );
    }

    public function actionToggle(ParameterBag $params)
    {
        $this->assertPostOnly();

        $package = $this->assertPackageExists($params->package_id);
        $package->active = $package->active ? 0 : 1;
        $package->save();

        return $this->redirect($this->buildLink('token-downloads/packages'));
    }

    /**
     * @param int $id
     * @param array $with
     *
     * @return \Jace\TokenDownloads\Entity\Package
     */
    protected function assertPackageExists($id, array $with = [])
    {
        $package = $this->em()->find('Jace\TokenDownloads:Package', $id, $with);
        if (!$package)
        {
            throw $this->exception($this->notFound(\XF::phrase('jace_token_downloads_requested_package_not_found')));
        }

        return $package;
    }

    /**
     * @return \Jace\TokenDownloads\Repository\Package
     */
    protected function getPackageRepo()
    {
        return $this->repository('Jace\TokenDownloads:Package');
    }
}