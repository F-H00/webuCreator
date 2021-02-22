<?php

namespace webu\system\core;

/*
 *  The default Class to store all Request Informations
 */

use webu\system\Core\Base\Helper\DatabaseHelper;
use webu\system\Core\Contents\ContentLoader;
use webu\system\Core\Contents\Context;
use webu\system\Core\Contents\Modules\ModuleController;
use webu\system\Core\Contents\Modules\ModuleLoader;
use webu\system\Core\Custom\Logger;
use webu\system\Core\Helper\CookieHelper;
use webu\system\Core\Helper\RoutingHelper;
use webu\system\Core\Helper\SessionHelper;
use webu\system\Environment;

class Request
{

    /** @var array */
    private $get = array();
    /** @var array */
    private $post = array();
    /** @var CookieHelper */
    private $cookies = null;
    /** @var SessionHelper */
    private $session = null;
    /** @var DatabaseHelper */
    private $database = null;
    /** @var RoutingHelper  */
    private $routingHelper = null;
    /** @var string */
    private $baseURI = '';
    /** @var string */
    private $requestURI = '';
    /** @var array */
    private $requestURIParams = array();
    /** @var array */
    private $compiledURIParams = array();
    /** @var string */
    private $requestController = 'index';
    /** @var string */
    private $requestActionPath = 'index';
    /** @var Context */
    private $context = null;


    public function __construct()
    {
    }

    public function gatherInformations()
    {
        //Load all Informations, found in the request
        $this->setParams();
        $this->setBaseURI();
        $this->setRequestURI();
        $this->setRequestURIParams();
        $this->compileURIParams();
    }

    public function addToAccessLog()
    {
        $text = 'Call to "';
        $text .= MAIN_ADDRESS . '/' . $this->requestURI;
        if (sizeof($this->requestURIParams)) {
            $text .= '" with the params ';
            $text .= implode(', ', $this->requestURIParams);
        }

        Logger::writeToAccessLog($text);
    }


    private function setRequestURIParams()
    {
        $params = explode("/", $this->requestURI);
        foreach ($params as &$param) {
            $param = strtolower(trim($param));
        }

        //get the controller
        if (sizeof($params) > 0) {
            $this->requestController = $params[0];
            array_shift($params);
        }


        /* Save the Action */
        if (sizeof($params) > 0) {
            $this->requestActionPath = $params[0];
            array_shift($params);
        }

        /* Save Params */
        if(sizeof($params) > 0) {
            $this->requestURIParams = $params;
        }
        else {
            $this->requestURIParams = [];
        }

    }

    private function compileURIParams() {
        $compiledParams = [];

        for($index = 0; $index < sizeof($this->requestURIParams); $index++) {
            $param = $this->requestURIParams[$index];


            if($index%2 == 1) {
                //jedes zweite element
                $prev = $this->requestURIParams[$index-1];
                $compiledParams[$prev] = $param;
            }
            else if($index == sizeof($this->requestURIParams)-1) {
                //letztes element, falls ungerade parameter anzahl
                $compiledParams[0] = $param;
            }


        }


        $this->compiledURIParams = $compiledParams;
    }

    private function setRequestURI()
    {
        $requestURI = $_SERVER['REQUEST_URI'];
        $getSeperator = strrpos($requestURI, '?');

        if ($getSeperator === false) {
            $this->requestURI = trim($requestURI, "/");
        }
        else {
            $this->requestURI = trim(substr($requestURI, 0, $getSeperator), "/");
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
    }



    public function loadController($requestController, $requestActionPath, Environment $e)
    {
        $moduleLoader = new ModuleLoader();
        $moduleCollection = $moduleLoader->loadModules(ROOT . "/modules");
        $moduleCollection->getURIList();

        $routingHelper = new RoutingHelper($moduleCollection);
        $result = $routingHelper->route($this->requestURI);

        if($result === false) {
            //TODO: Fallback für 404 einbinden
            die(__METHOD__);
        }

        /** @var ModuleController $controller */
        $controller = $result["controller"];
        /** @var string $method */
        $method = $result["method"];



        /* Insert gathered Information to Context */
        $this->fillContext();


        $params = [
            $this,
            $e->response
        ];

        //$controller->init($this,$e->response);

        $cls = $controller->getClass();
        $ctrl = new $cls();

        //call the controller method
        call_user_func_array(
            [
                $ctrl,
                $method
            ],
            $params
        );

        //$controller->end($this, $e->response);

    }

    private function fillContext() {

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
    public function getCookieHelper(): CookieHelper
    {
        return $this->cookies;
    }

    /** @return SessionHelper */
    public function getSessionHelper(): SessionHelper
    {
        return $this->session;
    }

    /** @return DatabaseHelper */
    public function getDatabaseHelper() : DatabaseHelper
    {
        return $this->database;
    }

    /**
     * @return RoutingHelper
     */
    public function getRoutingHelper() : RoutingHelper
    {
        return $this->routingHelper;
    }

    /**
     * @return string
     */
    public function getRequestController()
    {
        return $this->requestController;
    }

    /**
     * @return string
     */
    public function getRequestActionPath()
    {
        return $this->requestActionPath;
    }

    /**
     * @return string
     */
    public function getRequestURI()
    {
        return $this->requestURI;
    }

    /**
     * @return array
     */
    public function getRequestURIParams()
    {
        return $this->requestURIParams;
    }

    /**
     * @return array
     */
    public function getCompiledURIParams()
    {
        return $this->compiledURIParams;
    }

    /**
     * @return Context
     */
    public function getContext() {
        return $this->context;
    }


}