<?php

/**
 * \AppserverIo\Lab\MageServlet\MageStandardSessionServlet
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author    Tim Wagner <tw@techdivision.com>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io-lab/mageservlet
 * @link      http://www.appserver.io
 */

namespace AppserverIo\Lab\MageServlet;

use AppserverIo\Psr\HttpMessage\Protocol;
use AppserverIo\Psr\Servlet\ServletConfigInterface;
use AppserverIo\Psr\Servlet\Http\HttpServlet;
use AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface;
use AppserverIo\Psr\Servlet\Http\HttpServletResponseInterface;

/**
 * A servlet implementation for Magento that use the appserver.io standard session
 * handler instead of the Magente PHP standard session handling.
 *
 * CAUTION: This servlet is highly experimental is NOT production ready!
 *
 * @author    Tim Wagner <tw@techdivision.com>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io-lab/mageservlet
 * @link      http://www.appserver.io
 */
class MageStandardSessionServlet extends HttpServlet
{

    /**
     * The key to store the servlet request in the Magento registry.
     *
     * @var array
     */
    const SERVLET_REQUEST = 'Mage_Core_Model_Session_Abstract_Varien.Servlet_Request';

    /**
     * The servlet specific server variables.
     *
     * @var array
     */
    protected $serverVars = array();

    /**
     * The base directory of the actual webapp.
     *
     * @var string
     */
    protected $webappPath;

    /**
     * Initializes the servlet with the passed configuration.
     *
     * @param \AppserverIo\Psr\Servlet\ServletConfigInterface $config The configuration to initialize the servlet with
     *
     * @return void
     */
    public function init(ServletConfigInterface $config)
    {
        parent::init($config);
        $this->webappPath = $this->getServletConfig()->getWebappPath();
    }

    /**
     * Returns the base directory of the actual webapp.
     *
     * @return string The base directory
     */
    protected function getWebappPath()
    {
        return $this->webappPath;
    }

    /**
     * Returns the array with the $_FILES vars.
     *
     * @param \AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface $servletRequest The request instance
     *
     * @return array The $_FILES vars
     */
    protected function initFileGlobals(HttpServletRequestInterface $servletRequest)
    {

        // init query str
        $queryStr = '';

        // iterate all files
        foreach ($servletRequest->getParts() as $part) {
            // check if filename is given, write and register it
            if ($part->getFilename()) {
                // generate temp filename
                $tempName = tempnam(ini_get('upload_tmp_dir'), 'php');
                // write part
                $part->write($tempName);
                // register uploaded file
                $this->registerFileUpload($tempName);
                // init error state
                $errorState = UPLOAD_ERR_OK;
            } else {
                // set error state
                $errorState = UPLOAD_ERR_NO_FILE;
                // clear tmp file
                $tempName = '';
            }
            // check if file has array info
            if (preg_match('/^([^\[]+)(\[.+)?/', $part->getName(), $matches)) {

                // get first part group name and array definition if exists
                $partGroup = $matches[1];
                $partArrayDefinition = '';
                if (isset($matches[2])) {
                    $partArrayDefinition = $matches[2];
                }
                $queryStr .= $partGroup . '[name]' . $partArrayDefinition . '=' . $part->getFilename() .
                    '&' . $partGroup . '[type]' . $partArrayDefinition . '=' . $part->getContentType() .
                    '&' . $partGroup . '[tmp_name]' . $partArrayDefinition . '=' . $tempName .
                    '&' . $partGroup . '[error]' . $partArrayDefinition . '=' . $errorState .
                    '&' . $partGroup . '[size]' . $partArrayDefinition . '=' . $part->getSize() . '&';
            }
        }
        // parse query string to array
        parse_str($queryStr, $filesArray);

        // return files array finally
        return $filesArray;
    }

    /**
     * Register's a file upload on internal php hash table for being able to use core functions
     * like move_uploaded_file or is_uploaded_file as usual.
     *
     * @param string $filename The filename to register
     *
     * @return bool
     */
    public function registerFileUpload($filename)
    {
        return appserver_register_file_upload($filename);
    }

    /**
     * Returns the array with the $_COOKIE vars.
     *
     * @param \AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface $servletRequest The request instance
     *
     * @return array The $_COOKIE vars
     */
    protected function initCookieGlobals(HttpServletRequestInterface $servletRequest)
    {
        $cookie = array();
        foreach (explode(';', $servletRequest->getHeader(Protocol::HEADER_COOKIE)) as $cookieLine) {
            list ($key, $value) = explode('=', $cookieLine);
            $cookie[trim($key)] = trim($value);
        }
        return $cookie;
    }

    /**
     * Returns the array with the $_REQUEST vars.
     *
     * @param \AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface $servletRequest The request instance
     *
     * @return array The $_REQUEST vars
     */
    protected function initRequestGlobals(HttpServletRequestInterface $servletRequest)
    {
        return $servletRequest->getParameterMap();
    }

    /**
     * Returns the array with the $_POST vars.
     *
     * @param \AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface $servletRequest The request instance
     *
     * @return array The $_POST vars
     */
    protected function initPostGlobals(HttpServletRequestInterface $servletRequest)
    {
        if ($servletRequest->getMethod() == Protocol::METHOD_POST) {
            return $servletRequest->getParameterMap();
        } else {
            return array();
        }
    }

    /**
     * Returns the array with the $_GET vars.
     *
     * @param \AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface $servletRequest The request instance
     *
     * @return array The $_GET vars
     */
    protected function initGetGlobals(HttpServletRequestInterface $servletRequest)
    {
        // check post type and set params to globals
        if ($servletRequest->getMethod() == Protocol::METHOD_POST) {
            parse_str($servletRequest->getQueryString(), $parameterMap);
        } else {
            $parameterMap = $servletRequest->getParameterMap();
        }
        return $parameterMap;
    }

    /**
     * Returns the $_SESSION vars, in that case always NULL, because Magento
     * expects $_SESSION to be NULL to start the session as expected.
     *
     * @param \AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface $servletRequest The request instance
     *
     * @return array The $_SESSION vars, NULL in that case
     */
    protected function initSessionGlobals(HttpServletRequestInterface $servletRequest)
    {
        return null;
    }

    /**
     * Initialize the PHP globals necessary for legacy mode and backward compatibility
     * for standard applications.
     *
     * @param \AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface $servletRequest The request instance
     *
     * @return void
     */
    protected function initGlobals(HttpServletRequestInterface $servletRequest)
    {

        // prepare the request before initializing the globals
        $this->prepareGlobals($servletRequest);

        // initialize the globals
        $_SERVER = $this->initServerGlobals($servletRequest);
        $_REQUEST = $this->initRequestGlobals($servletRequest);
        $_POST = $this->initPostGlobals($servletRequest);
        $_GET = $this->initGetGlobals($servletRequest);
        $_COOKIE = $this->initCookieGlobals($servletRequest);
        $_FILES = $this->initFileGlobals($servletRequest);
        $_SESSION = $this->initSessionGlobals($servletRequest);
    }

    /**
     * Tries to load the requested file and adds the content to the response.
     *
     * @param \AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface  $servletRequest  The request instance
     * @param \AppserverIo\Psr\Servlet\Http\HttpServletResponseInterface $servletResponse The response instance
     *
     * @return void
     */
    public function doGet(HttpServletRequestInterface $servletRequest, HttpServletResponseInterface $servletResponse)
    {

        // load \Mage
        $this->load();

        // init globals
        $this->initGlobals($servletRequest);

        // run \Mage and set content
        $content = $this->run($servletRequest);
        $servletResponse->appendBodyStream($content);

        $this->prepareResponse($servletRequest, $servletResponse);
    }

    public function prepareResponse(HttpServletRequestInterface $servletRequest, HttpServletResponseInterface $servletResponse)
    {

        error_log("Now in " . __METHOD__ . " handling request " . $servletRequest->getUri());

        // load the session and persist the data from $_SESSION
        $session = $servletRequest->getSession();

        if ($session != null && isset($_SESSION)) {

            foreach ($_SESSION as $namespace => $data) {
                if ($namespace !== 'identifier') {
                    error_log("Now add data for session {$session->getId()} and namespace $namespace: ". PHP_EOL . var_export($data, true));
                    $session->putData($namespace, $data);
                }
            }
        }

        // add the status code we've caught from the legacy app
        $servletResponse->setStatusCode(appserver_get_http_response_code());

        // add this header to prevent .php request to be cached
        $servletResponse->addHeader(Protocol::HEADER_EXPIRES, '19 Nov 1981 08:52:00 GMT');
        $servletResponse->addHeader(Protocol::HEADER_CACHE_CONTROL, 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $servletResponse->addHeader(Protocol::HEADER_PRAGMA, 'no-cache');

        // set per default text/html mimetype
        $servletResponse->addHeader(Protocol::HEADER_CONTENT_TYPE, 'text/html');

        error_log("============= RESPONSE before appserver_get_headers(true) =================");
        error_log(var_export($servletResponse, true));

        // grep headers and set to response object
        foreach (appserver_get_headers(true) as $i => $h) {
            // set headers defined in sapi headers
            $h = explode(':', $h, 2);
            if (isset($h[1])) {
                // load header key and value
                $key = trim($h[0]);
                $value = trim($h[1]);
                // if no status, add the header normally
                if ($key === Protocol::HEADER_STATUS) {
                    // set status by Status header value which is only used by fcgi sapi's normally
                    $servletResponse->setStatus($value);
                } elseif ($key === Protocol::HEADER_SET_COOKIE) {
                    $servletResponse->addHeader($key, $value, true);
                } else {
                    $servletResponse->addHeader($key, $value);
                }
            }
        }

        error_log("============= RESPONSE after appserver_get_headers(true) =================");
        error_log(var_export($servletResponse, true));
    }

    /**
     * Tries to load the requested file and adds the content to the response.
     *
     * @param \AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface  $servletRequest  The request instance
     * @param \AppserverIo\Psr\Servlet\Http\HttpServletResponseInterface $servletResponse The response instance
     *
     * @return void
     */
    public function doPost(HttpServletRequestInterface $servletRequest, HttpServletResponseInterface $servletResponse)
    {
        $this->doGet($servletRequest, $servletResponse);
    }

    /**
     * Prepares the passed request instance for generating the globals.
     *
     * @param \AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface $servletRequest The request instance
     *
     * @return void
     */
    protected function prepareGlobals(HttpServletRequestInterface $servletRequest)
    {

        // load the requested script name
        $scriptName = basename($servletRequest->getServerVar('SCRIPT_NAME'));

        // if the application has not been called over a vhost configuration append application folder name
        if ($servletRequest->getContext()->isVhostOf($servletRequest->getServerName()) === false) {
            $scriptName = $servletRequest->getContextPath() . DIRECTORY_SEPARATOR . $scriptName;
        }

        // initialize the server variables
        $this->serverVars['PHP_SELF'] = $scriptName;

        // ATTENTION: This is necessary because of a Magento bug!!!!
        $this->serverVars['SERVER_PORT'] = null;
    }

    /**
     * Returns the array with the $_SERVER vars.
     *
     * @param \AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface $servletRequest The request instance
     *
     * @return array The $_SERVER vars
     */
    protected function initServerGlobals(HttpServletRequestInterface $servletRequest)
    {
        return array_merge($servletRequest->getServerVars(), $this->serverVars);
    }

    /**
     * Loads the necessary files needed.
     *
     * @return void
     */
    public function load()
    {
        require_once $this->getServletConfig()->getWebappPath() . '/app/Mage.php';
    }

    /**
     * Runs the WebApplication
     *
     * @param \AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface $servletRequest The request instance
     *
     * @return string The web applications content
     */
    public function run(HttpServletRequestInterface $servletRequest)
    {

        try {

            // register the Magento autoloader as FIRST autoloader
            spl_autoload_register(array(new \Varien_Autoload(), 'autoload'), true, true);

            // Varien_Profiler::enable();
            if (isset($_SERVER['MAGE_IS_DEVELOPER_MODE'])) {
                \Mage::setIsDeveloperMode(true);
            }

            ini_set('display_errors', 1);
            umask(0);

            // store or website code
            $mageRunCode = isset($_SERVER['MAGE_RUN_CODE']) ? $_SERVER['MAGE_RUN_CODE'] : '';

            // run store or run website
            $mageRunType = isset($_SERVER['MAGE_RUN_TYPE']) ? $_SERVER['MAGE_RUN_TYPE'] : 'store';

            // set headers sent to false and start output caching
            appserver_set_headers_sent(false);
            ob_start();

            // reset and run Magento
            \Mage::reset();
            \Mage::register(MageStandardSessionServlet::SERVLET_REQUEST, $servletRequest);
            \Mage::run();

            /*
            // load the session and persist the data from $_SESSION
            $session = $servletRequest->getSession();

            foreach ($_SESSION as $namespace => $data) {
                if ($namespace !== 'identifier') {
                    error_log("Now add data for session {$session->getId()} and namespace: ". PHP_EOL . var_export($dat));
                    $session->putData($namespace, $data);
                }
            }
            */

            // grab the contents generated by Magento
            $content = ob_get_clean();

        } catch (\Exception $e) {
            error_log($content = $e->__toString());
        }

        // return the content
        return $content;
    }
}
