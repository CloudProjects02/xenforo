<?php

namespace SV\SignupAbuseBlocking\Admin\Controller;

use SV\SignupAbuseBlocking\ControllerPlugin\Csv as CsvControllerPlugin;
use SV\SignupAbuseBlocking\Repository\AbstractAllowOrBanItem as ItemRepo;
use SV\StandardLib\Helper;
use XF\Admin\Controller\AbstractController;
use XF\ControllerPlugin\Xml as XmlControllerPlugin;
use XF\Db\AbstractAdapter as DbAdapter;
use XF\Entity\User as UserEntity;
use XF\Finder\User as UserFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure as EntityStructure;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View as ViewReply;
use SV\SignupAbuseBlocking\Finder\AbstractAllowOrBanItem as ItemFinder;
use function preg_replace;

/**
 * Class AbstractAllowOrBan
 *
 * @package SV\SignupAbuseBlocking\Admin\Controller
 */
abstract class AbstractAllowOrBan extends AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('svAntiSpam');
    }

    abstract protected function getIdentifier(): string;

    protected function getViewInfix(): string
    {
        return \explode(':', $this->getIdentifier(), 2)[1];
    }

    abstract protected function getRouteInfix(): string;

    protected function getTemplateInfix(): string
    {
        return $this->toSnakeCase(\explode(':', $this->getIdentifier())[1]);
    }

    protected function getXmlRootName(): string
    {
        return \str_replace('-', '_', $this->getRouteInfix());
    }

    protected function getExportFileName(string $extension): string
    {
        return $this->getXmlRootName() . ($extension ? '.' . $extension : '');
    }

    protected function getXmlFileName(): string
    {
        return $this->getExportFileName('xml');
    }

    protected function getCsvFileName(): string
    {
        return $this->getExportFileName('csv');
    }

    /**
     * @since 1.8.13
     *
     * @param string|array $usernames
     *
     * @return AbstractCollection<UserEntity>
     */
    protected function getUsersByUsername($usernames) : AbstractCollection
    {
        if (\is_string($usernames))
        {
            $usernames = \explode(',', $usernames);
        }
        $usernames = \array_filter($usernames, 'trim');

        return Helper::finder(UserFinder::class)->where('username', $usernames)->fetch();
    }

    /**
     * @since 1.8.13
     *
     * @param array $userIds
     *
     * @return AbstractCollection<UserEntity>
     */
    protected function getUsersByIds(array $userIds) : AbstractCollection
    {
        return Helper::finder(UserFinder::class)->where('user_id', $userIds)->fetch();
    }

    /**
     * @since 1.8.13
     *
     * @param string $creatorsFilter
     *
     * @return array
     */
    protected function getFilterInput(string &$creatorsFilter = '') : array
    {
        $filters = [];

        $primaryKey = $this->getStructure()->primaryKey;
        $creators = $this->em()->getEmptyCollection();

        $input = $this->filter([
            $primaryKey => 'str',
            'creators' => 'str',
            'create_user_ids' => 'array-uint',
            'reason' => 'str',
            'create_date_start' => 'datetime'
        ]);

        if (\strlen($input[$primaryKey]) !== 0)
        {
            $filters[$primaryKey] = $input[$primaryKey];
        }

        if ($input['create_user_ids'])
        {
            $filters['create_user_ids'] = $input['create_user_ids'];
            if (\count($filters['create_user_ids']))
            {
                $creators = $this->getUsersByIds($filters['create_user_ids']);
            }
        }
        else if ($input['creators'])
        {
            $creators = $this->getUsersByUsername($input['creators']);
            if ($creators->count())
            {
                $filters['create_user_ids'] = $creators->keys();
            }
        }

        if ($creators->count())
        {
            $creatorsFilter = \implode(', ', \array_keys($creators->groupBy('username')));
            $creatorsFilter .= ', ';
        }

        if (\strlen($input['reason']) !== 0)
        {
            $filters['reason'] = $input['reason'];
        }

        if (!empty($input['create_date_start']))
        {
            $filters['create_date_start'] = $input['create_date_start'];
        }

        return $filters;
    }

    /**
     * @param ItemFinder $finder
     * @param array $filters
     *
     * @returns void
     */
    protected function applyFilters(ItemFinder $finder, array $filters)
    {
        $primaryKey = $this->getStructure()->primaryKey;

        if (\array_key_exists($primaryKey, $filters))
        {
            $finder->where(
                $primaryKey,
                'LIKE',
                $finder->escapeLike($filters[$primaryKey], '%?%')
            );
        }

        if (\array_key_exists('create_user_ids', $filters))
        {
            $finder->where('create_user_id', $filters['create_user_ids']);
        }

        if (\array_key_exists('reason', $filters))
        {
            $finder->where(
                'reason',
                'LIKE',
                $finder->escapeLike($filters['reason'], '%?%')
            );
        }

        if (\array_key_exists('create_date_start', $filters))
        {
            $finder->where(
                'create_date',
                '>=',
                $filters['create_date_start']
            );
        }
    }

    public function actionIndex(): AbstractReply
    {
        $creatorsFilter = '';
        $filters = $this->getFilterInput($creatorsFilter);
        if ($this->filter('apply', 'bool'))
        {
            return $this->redirect($this->buildLink('banning/allowed-email-domains', null, $filters));
        }

        $primaryKey = $this->getPrimaryKey();

        $page = $this->filterPage();
        $perPage = 20;

        $order = $this->filter('order', 'str', 'create_date');
        $direction = $this->filter('direction', 'str', 'desc');

        $orderFields = [
            [$order, $direction]
        ];
        if ($order !== $primaryKey)
        {
            // If not already set, add this as a secondary sort because
            // majority of fields may be blank (especially legacy data)
            $orderFields[] = [$primaryKey, 'asc'];
        }

        $itemRepo = $this->getItemRepo($this->getIdentifier());
        $itemFinder = $itemRepo->findItems()
            ->with('User')
            ->order($orderFields);

        $this->applyFilters($itemFinder, $filters);

        $itemFinder->limitByPage($page, $perPage);
        $total = $itemFinder->total();

        $this->assertValidPage($page, $perPage, $total, "banning/{$this->getRouteInfix()}", $filters);

        $viewParams = [
            'items' => $itemFinder->fetch(),

            'filters' => $filters,
            'creatorsFilter' => $creatorsFilter,

            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,

            'order' => $order,
            'direction' => $direction,

            'newItem' => $this->em()->create($this->getIdentifier()),

            'routeInfix' => $this->getRouteInfix(),
            'primaryKey' => $primaryKey
        ];
        return $this->view(
            "SV\SignupAbuseBlocking\\XF:Banning\\{$this->getViewInfix()}\\Listing",
            "svSignupAbuseBlocking_{$this->getTemplateInfix()}_list",
            $viewParams
        );
    }

    public function actionAdd(): AbstractReply
    {
        $this->assertPostOnly();

        $itemRepo = $this->getItemRepo($this->getIdentifier());
        $itemRepo->addItem(
            $this->filter('email_domain', 'str'),
            $this->filter('reason', 'str')
        );

        return $this->redirect($this->buildLink("banning/{$this->getRouteInfix()}"));
    }

    public function actionExport(): AbstractReply
    {
        $itemRepo = $this->getItemRepo($this->getIdentifier());
        $itemFinder = $itemRepo->findItems();

        switch ($this->filter('type', 'str', 'xml'))
        {
            case 'xml':
                $reply = $this->getXmlControllerPlugin()->actionExport(
                    $itemFinder,
                    "{$this->getIdentifier()}\XmlExporter",
                    'SV\SignupAbuseBlocking:AllowOrBanItem\XmlExport'
                );

                if ($reply instanceof ViewReply)
                {
                    $reply->setParam('xmlFileName', $this->getXmlFileName());
                }
                return $reply;

            case 'csv':
                $reply = $this->getCsvControllerPlugin()->actionExport(
                    $itemFinder,
                    "{$this->getIdentifier()}\CsvExporter",
                    'SV\SignupAbuseBlocking:AllowOrBanItem\CsvExport'
                );

                if ($reply instanceof ViewReply)
                {
                    $reply->setParam('csvFileName', $this->getCsvFileName());
                }
                return $reply;
        }

        throw $this->exception($this->error(
            \XF::phrase('svSignupAbuseBlocking_invalid_import_export_type_selected')
        ));
    }

    public function actionImport(): AbstractReply
    {
        switch ($this->filter('type', 'str', 'xml'))
        {
            case 'xml':
                return $this->getXmlControllerPlugin()->actionImport(
                    "banning/{$this->getRouteInfix()}",
                    $this->getXmlRootName(),
                    "{$this->getIdentifier()}\XmlImporter"
                );

            case 'csv':
                return $this->getCsvControllerPlugin()->actionImport(
                    "banning/{$this->getRouteInfix()}",
                    "{$this->getIdentifier()}\CsvImporter"
                );
        }

        throw $this->exception($this->error(
            \XF::phrase('svSignupAbuseBlocking_invalid_import_export_type_selected')
        ));
    }

    public function actionDelete(): AbstractReply
    {
        $this->assertPostOnly();

        $deletes = $this->filter('delete', 'array-str');

        $db = $this->db();
        $db->beginTransaction();

        $items = $this->em()->findByIds($this->getIdentifier(), $deletes);
        foreach ($items AS $item)
        {
            /** @var Entity $item */
            $item->delete(true, false);
        }

        $db->commit();

        return $this->redirect($this->buildLink("banning/{$this->getRouteInfix()}"));
    }

    protected function toSnakeCase(string $str): string
    {
        $firstChar = mb_strtolower(mb_substr($str, 0, 1));
        $rest = mb_substr($str, 1);
        $str = $firstChar . $rest;

        return mb_strtolower(preg_replace('/[A-Z]/u', '_\\0', $str));
    }

    protected function getItemRepo(string $identifier): ItemRepo
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->app()->repository($identifier);
    }

    protected function getCsvControllerPlugin(): CsvControllerPlugin
    {
        return Helper::plugin($this, CsvControllerPlugin::class);
    }

    protected function getXmlControllerPlugin(): XmlControllerPlugin
    {
        return Helper::plugin($this,XmlControllerPlugin::class);
    }

    /**
     * @return string|int
     */
    protected function getPrimaryKey()
    {
        return $this->getStructure()->primaryKey;
    }

    protected function getStructure(): EntityStructure
    {
        return $this->em()->getEntityStructure($this->getIdentifier());
    }

    protected function db(): DbAdapter
    {
        return $this->app()->db();
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
