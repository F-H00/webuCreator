<?php

namespace webu\system\core;

/*
 *  The default Class to store all Request Informations
 */

use webu\system\Core\Base\Controller\BaseController;
use webu\system\Core\Base\Controller\ControllerInterface;
use webu\system\Core\Base\Helper\DatabaseHelper;
use webu\system\Core\Contents\ContentLoader;
use webu\system\Core\Contents\Context;
use webu\system\Core\Contents\Modules\Module;
use webu\system\Core\Contents\Modules\ModuleCollection;
use webu\system\Core\Contents\Modules\ModuleController;
use webu\system\Core\Contents\Modules\ModuleLoader;
use webu\system\Core\Contents\Modules\ModuleNamespacer;
use webu\system\Core\Contents\ValueBag;
use webu\system\Core\Custom\Logger;
use webu\system\Core\Helper\CookieHelper;
use webu\system\Core\Helper\FrameworkHelper\CUriConverter;
use webu\system\Core\Helper\RoutingHelper;
use webu\system\Core\Helper\SessionHelper;
use webu\system\Core\Helper\URIHelper;
use webu\system\Core\Helper\XMLReader;
use webu\system\Core\Services\Service;
use webu\system\Core\Services\ServiceContainer;
use webu\system\Core\Services\ServiceLoader;
use webu\system\Core\Services\ServiceTags;
use webu\system\Environment;
use webu\system\Throwables\ModulesNotLoadedException;
use webu\system\Throwables\NoModuleFoundException;
use webuApp\Models\RewriteUrl;

class Request
{

    private ?Environment $environment = null;
    private array $get = array();
    private array $post = array();
    private ?CookieHelper $cookies = null;
    private ?SessionHelper $session = null;
    private ?DatabaseHelper $database = null;
    private ?RoutingHelper $routingHelper = null;
    private string $baseURI = '';
    private string $requestURI = '';
    private ?Context $context = null;
    private ?ModuleCollection $moduleCollection = null;
    private ?ServiceContainer $serviceContainer = null;
    private ValueBag $curlValueBag;


    public function __construct(Environment $environment)
    {
        $this->environment = $environment;
        $this->curlValueBag = new ValueBag();
    }


    public function gatherInformationsFromRequest()
    {
        //Load all Informations, found in the request
        $this->setParams();
        $this->setBaseURI();
        $this->setRequestURI();
        $this->checkRewriteURL();
    }

    public function checkRewriteURL() {

        $newURL = $this->getRoutingHelper()->rewriteURL(
            $this->requestURI,
            RewriteUrl::loadAll($this->getDatabaseHelper()),
            $this->curlValueBag
        );


        $this->requestURI;

        $parts = parse_url($newURL);
        parse_str($parts['query'], $query);

        foreach($query as $key => $value) {
            $this->get[$key] = $value;
        }
    }

    public function addToAccessLog()
    {
        $text = 'Call to "';
        $text .= MAIN_ADDRESS . '/' . $this->requestURI;
        if (sizeof($this->get)) {
            $text .= '" with the params ';
            $text .= implode(', ', $this->get);
        }

        Logger::writeToAccessLog($text);
    }



    private function setRequestURI()
    {
        $requestURI = $_SERVER['REQUEST_URI'];
        $getSeperatorPosition = strrpos($requestURI, '?');

        if ($getSeperatorPosition === false) {
            $this->requestURI = trim($requestURI, "/");
        }
        else {
            $this->requestURI = trim(substr($requestURI, 0, $getSeperatorPosition), "/");
        }

    }

    private function setBaseURI()
    {
        if (!empty($_SERVER['HTTPS']) && ('on' == $_SERVER['HTTPS'])) {
            $uri = 'https://';
        } else {
            $uri = 'http://';
        }
        $uri .= $_SERVER['HTTP_HOST'];

        $this->baseURI = $uri; //e.g. 'http://localhost//'
    }


    private function setParams()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->cookies = new CookieHelper();
        $this->session = new SessionHelper();
        $this->database = new DatabaseHelper();
        $this->context = new Context();

        $moduleLoader = new ModuleLoader();
        //$moduleLoader->readModules();
        $this->moduleCollection = $moduleLoader->loadModules($this->getDatabaseHelper()->getConnection());

        $serviceLoader = new ServiceLoader();
        $this->serviceContainer = $serviceLoader->loadServices($this->moduleCollection);

        $this->routingHelper = new RoutingHelper($this->serviceContainer);
    }

    public function addCoreServices() {

        $this->serviceContainer->addService(
            (new Service())->setId('system.cookie.helper')
                ->setStatic(true)
                ->setTag(ServiceTags::BASE_SERVICE_STATIC)
                ->setClass('webu\system\Core\Helper\CookieHelper')
                ->setInstance($this->getCookieHelper())
        );

        $this->serviceContainer->addService(
            (new Service())->setId('system.session.helper')
                ->setStatic(true)
                ->setTag(ServiceTags::BASE_SERVICE_STATIC)
                ->setClass('webu\system\Core\Helper\SessionHelper')
                ->setInstance($this->getSessionHelper())
        );

        $this->serviceContainer->addService(
            (new Service())->setId('system.database.helper')
                ->setStatic(true)
                ->setTag(ServiceTags::BASE_SERVICE_STATIC)
                ->setClass('webu\system\Core\Helper\DatabaseHelper')
                ->setInstance($this->getDatabaseHelper())
        );

        $this->serviceContainer->addService(
            (new Service())->setId('system.database.connection')
                ->setStatic(true)
                ->setTag(ServiceTags::BASE_SERVICE_STATIC)
                ->setClass('webu\system\Core\Base\Database\DatabaseConnection')
                ->setInstance($this->getDatabaseHelper()->getConnection())
        );

        $this->serviceContainer->addService(
            (new Service())->setId('system.routing.helper')
                ->setStatic(true)
                ->setTag(ServiceTags::BASE_SERVICE_STATIC)
                ->setClass('webu\system\Core\Helper\RoutingHelper')
                ->setInstance($this->getRoutingHelper())
        );

        $this->serviceContainer->addService(
            (new Service())->setId('system.twig.helper')
                ->setStatic(true)
                ->setTag(ServiceTags::BASE_SERVICE_STATIC)
                ->setClass('webu\system\Core\Helper\TwigHelper')
                ->setInstance($this->environment->response->getTwigHelper())
        );

        $this->serviceContainer->addService(
            (new Service())->setId('system.scss.helper')
                ->setStatic(true)
                ->setTag(ServiceTags::BASE_SERVICE_STATIC)
                ->setClass('webu\system\Core\Helper\ScssHelper')
                ->setInstance($this->environment->response->getScssHelper())
        );

        $this->serviceContainer->addService(
            (new Service())->setId('system.xml.helper')
                ->setStatic(true)
                ->setTag(ServiceTags::BASE_SERVICE_STATIC)
                ->setClass('webu\system\Core\Helper\XMLReader')
                ->setInstance(new XMLReader())
        );

        $this->serviceContainer->addService(
            (new Service())->setId('system.curi.converter.helper')
                ->setStatic(true)
                ->setTag(ServiceTags::BASE_SERVICE_STATIC)
                ->setClass('webu\system\Core\Helper\FrameworkHelper\CUriConverter')
                ->setInstance(new CUriConverter())
        );

        $this->serviceContainer->addService(
            (new Service())->setId('system.file.editor.helper')
                ->setStatic(true)
                ->setTag(ServiceTags::BASE_SERVICE_STATIC)
                ->setClass('webu\system\Core\Base\Custom\FileEditor')
        );

        $this->serviceContainer->addService(
            (new Service())->setId('system.logger.helper')
                ->setStatic(true)
                ->setTag(ServiceTags::BASE_SERVICE_STATIC)
                ->setClass('webu\system\Core\Base\Custom\Logger')
        );

        $this->serviceContainer->addService(
            (new Service())->setId('system.string.converter.helper')
                ->setStatic(true)
                ->setTag(ServiceTags::BASE_SERVICE_STATIC)
                ->setClass('webu\system\Core\Base\Custom\StringConverter')
        );

        $this->serviceContainer->addService(
            (new Service())->setId('system.query.builder.helper')
                ->setStatic(true)
                ->setTag(ServiceTags::BASE_SERVICE_STATIC)
                ->setClass('webu\system\Core\Base\Database\Query\QueryBuilder')
        );

        $this->serviceContainer->addService(
            (new Service())->setId('system.request.curi.valuebag')
                ->setStatic(true)
                ->setInstance($this->getCurlValueBag())
                ->setTag(ServiceTags::BASE_SERVICE_STATIC)
                ->setClass('webu\system\Core\Contents\ValueBag')
        );


    }


    public function loadController()
    {
        /** @var Service $controllerService */
        $controllerService = null;
        /** @var string $actionMethod */
        $actionMethod = null;

        $routingHelper = new RoutingHelper($this->serviceContainer);
        $routingHelper->route(
            $this->get["controller"] ?? "",
            $this->get["action"] ?? "",
            $controllerService,
            $actionMethod
        );

        $controllerInstance = $controllerService->getInstance();
        $controllerInstance->$actionMethod(); //pass uri parameters



        $this->environment->response->getTwigHelper()->assign("environment", MODE);
        $this->environment->response->getScssHelper()->setBaseVariable("assetsPath", URIHelper::createPath([
            MAIN_ADDRESS_FULL,CACHE_DIR,"public","assets"
        ], "/"));


    }


    protected function fillContext() {
        $contentLoader = new ContentLoader($this);
        $contentLoader->init($this->getContext());
    }



    /*
     * Getter & Setter Methoden
     */

    /** @return array */
    public function getParamGet(): array
    {
        return $this->get;
    }

    /** @return array */
    public function getParamPost(): array
    {
        return $this->post;
    }

    /** @return CookieHelper */
    public function getCookieHelper(): ?CookieHelper
    {
        return $this->cookies;
    }

    /** @return SessionHelper */
    public function getSessionHelper(): ?SessionHelper
    {
        return $this->session;
    }

    /** @return DatabaseHelper */
    public function getDatabaseHelper() : ?DatabaseHelper
    {
        return $this->database;
    }

    /**
     * @return RoutingHelper
     */
    public function getRoutingHelper() : ?RoutingHelper
    {
        return $this->routingHelper;
    }

    /**
     * @return string
     */
    public function getRequestURI()
    {
        return $this->requestURI;
    }


    /**
     * @return Context
     */
    public function getContext() {
        return $this->context;
    }


    public function getModuleCollection() : ?ModuleCollection {
        return $this->moduleCollection;
    }

    public function getCurlValueBag(): ?ValueBag
    {
        return $this->curlValueBag;
    }





}