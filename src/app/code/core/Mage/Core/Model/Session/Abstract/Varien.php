<?php

class Mage_Core_Model_Session_Abstract_Varien extends Varien_Object
{

    const VALIDATOR_KEY                         = '_session_validator_data';
    const VALIDATOR_HTTP_USER_AGENT_KEY         = 'http_user_agent';
    const VALIDATOR_HTTP_X_FORVARDED_FOR_KEY    = 'http_x_forwarded_for';
    const VALIDATOR_HTTP_VIA_KEY                = 'http_via';
    const VALIDATOR_REMOTE_ADDR_KEY             = 'remote_addr';

    const SERVLET_REQUEST = 'Mage_Core_Model_Session_Abstract_Varien.Servlet_Request';
    const SESSION = 'Mage_Core_Model_Session_Abstract_Varien.Session';

    /**
     * Configure and start session
     *
     * @param string $sessionName
     * @return Mage_Core_Model_Session_Abstract_Varien
     */
    public function start($sessionName = null)
    {

        if (isset($_SESSION) && !$this->getSkipEmptySessionCheck()) {
            return $this;
        }

        Varien_Profiler::start(__METHOD__.'/start');

        $cookie = $this->getCookie();
        if (Mage::app()->getStore()->isAdmin()) {
            $sessionMaxLifetime = Mage_Core_Model_Resource_Session::SEESION_MAX_COOKIE_LIFETIME;
            $adminSessionLifetime = (int)Mage::getStoreConfig('admin/security/session_cookie_lifetime');
            if ($adminSessionLifetime > $sessionMaxLifetime) {
                $adminSessionLifetime = $sessionMaxLifetime;
            }
            if ($adminSessionLifetime > 60) {
                $cookie->setLifetime($adminSessionLifetime);
            }
        }

        // session cookie params
        $cookieParams = array(
            'lifetime' => $cookie->getLifetime(),
            'path'     => $cookie->getPath(),
            'domain'   => $cookie->getConfigDomain(),
            'secure'   => $cookie->isSecure(),
            'httponly' => $cookie->getHttponly()
        );

        if (!$cookieParams['httponly']) {
            unset($cookieParams['httponly']);
            if (!$cookieParams['secure']) {
                unset($cookieParams['secure']);
                if (!$cookieParams['domain']) {
                    unset($cookieParams['domain']);
                }
            }
        }

        if (isset($cookieParams['domain'])) {
            $cookieParams['domain'] = $cookie->getDomain();
        }

        $servletRequest = Mage::registry(self::SERVLET_REQUEST);

        $settings = $servletRequest->getContext()->getSessionManager()->getSettings();
        $settings->setSessionCookieLifetime($cookieParams['lifetime']);
        $settings->setSessionCookiePath($cookieParams['path']);
        $settings->setSessionCookieDomain($cookieParams['domain']);
        $settings->setSessionCookieSecure($cookieParams['secure']);
        $settings->setSessioncookieHttpOnly($cookieParams['httponly']);

        error_log(var_export($cookieParams, true));

        $servletRequest->setRequestedSessionName($sessionName);
        $session = $servletRequest->getSession(true);
        $session->start();

        $_SESSION = array();
        foreach ($session->getSession()->data as $namespace => $data) {
            if ($namespace !== 'identifier') {
                error_log("Now re-attach data for namespace $namespace: " . PHP_EOL . var_export($data, true));
                $_SESSION[$namespace] = $data;
            }
        }

        Mage::register(self::SESSION, $session);

        error_log("Successfully started appserver.io session with ID: " . $session->getId());

        /*
         * Renew cookie expiration time if session id did not change
        *
        * @TODO To implement
        *
        * $cookie = $servletRequest->getCookie($sessionName);
        * if ($cookie->getValue() == $this->getSessionId()) {
        *    $cookie->renew($sessionName);
        * }
        *
        */

        Varien_Profiler::stop(__METHOD__.'/start');

        return $this;
    }

    /**
     * Retrieve cookie object
     *
     * @return Mage_Core_Model_Cookie
     */
    public function getCookie()
    {
        return Mage::getSingleton('core/cookie');
    }

    /**
     * Revalidate cookie
     * @deprecated after 1.4 cookie renew moved to session start method
     * @return Mage_Core_Model_Session_Abstract_Varien
     */
    public function revalidateCookie()
    {
        return $this;
    }

    /**
     * Init session with namespace
     *
     * @param string $namespace
     * @param string $sessionName
     * @return Mage_Core_Model_Session_Abstract_Varien
     */
    public function init($namespace, $sessionName = null)
    {

        if (!isset($_SESSION)) {
            $this->start($sessionName);
        }

        if (!isset($_SESSION[$namespace])) {
            $_SESSION[$namespace] = array();
        }

        $this->_data = &$_SESSION[$namespace];

        $this->validate();
        $this->revalidateCookie();

        return $this;
    }

    /**
     * Additional get data with clear mode
     *
     * @param string $key
     * @param bool $clear
     * @return mixed
     */
    public function getData($key='', $clear = false)
    {
        $data = parent::getData($key);
        if ($clear && isset($this->_data[$key])) {
            unset($this->_data[$key]);
        }
        return $data;
    }

    /**
     * Retrieve session Id
     *
     * @return string
     */
    public function getSessionId()
    {
        return Mage::registry(self::SESSION)->getId();
    }

    /**
     * Set custom session id
     *
     * @param string $id
     * @return Mage_Core_Model_Session_Abstract_Varien
     */
    public function setSessionId($id = null)
    {
        return $this;
    }

    /**
     * Retrieve session name
     *
     * @return string
     */
    public function getSessionName()
    {
        return Mage::registry(self::SESSION)->getName();
    }

    /**
     * Set session name
     *
     * @param string $name
     * @return Mage_Core_Model_Session_Abstract_Varien
     */
    public function setSessionName($sessionName)
    {
        return $this;
    }

    /**
     * Unset all data
     *
     * @return Mage_Core_Model_Session_Abstract_Varien
     */
    public function unsetAll()
    {
        $this->unsetData();
        return $this;
    }

    /**
     * Alias for unsetAll
     *
     * @return Mage_Core_Model_Session_Abstract_Varien
     */
    public function clear()
    {
        return $this->unsetAll();
    }

    /**
     * Retrieve session save method
     * Default files
     *
     * @return string
     */
    public function getSessionSaveMethod()
    {
        return 'files';
    }

    /**
     * Get sesssion save path
     *
     * @return string
     */
    public function getSessionSavePath()
    {
        return Mage::getBaseDir('session');
    }

    /**
     * Use REMOTE_ADDR in validator key
     *
     * @return bool
     */
    public function useValidateRemoteAddr()
    {
        return true;
    }

    /**
     * Use HTTP_VIA in validator key
     *
     * @return bool
     */
    public function useValidateHttpVia()
    {
        return true;
    }

    /**
     * Use HTTP_X_FORWARDED_FOR in validator key
     *
     * @return bool
     */
    public function useValidateHttpXForwardedFor()
    {
        return true;
    }

    /**
     * Use HTTP_USER_AGENT in validator key
     *
     * @return bool
     */
    public function useValidateHttpUserAgent()
    {
        return true;
    }

    /**
     * Retrieve skip User Agent validation strings (Flash etc)
     *
     * @return array
     */
    public function getValidateHttpUserAgentSkip()
    {
        return array();
    }

    /**
     * Validate session
     *
     * @param string $namespace
     * @return Mage_Core_Model_Session_Abstract_Varien
     */
    public function validate()
    {
        if (!isset($this->_data[self::VALIDATOR_KEY])) {
            $this->_data[self::VALIDATOR_KEY] = $this->getValidatorData();
        } else {
            if (!$this->_validate()) {
                Mage::registry(self::SESSION)->destroy('Found invalid session data');
                // throw core session exception
                throw new Mage_Core_Model_Session_Exception('');
            }
        }
        return $this;
    }

    /**
     * Validate data
     *
     * @return bool
     */
    protected function _validate()
    {

        $sessionData = $this->_data[self::VALIDATOR_KEY];
        $validatorData = $this->getValidatorData();

        if ($this->useValidateRemoteAddr()
            && $sessionData[self::VALIDATOR_REMOTE_ADDR_KEY] != $validatorData[self::VALIDATOR_REMOTE_ADDR_KEY]) {
                return false;
            }
            if ($this->useValidateHttpVia()
                && $sessionData[self::VALIDATOR_HTTP_VIA_KEY] != $validatorData[self::VALIDATOR_HTTP_VIA_KEY]) {
                    return false;
                }

                $sessionValidateHttpXForwardedForKey = $sessionData[self::VALIDATOR_HTTP_X_FORVARDED_FOR_KEY];
                $validatorValidateHttpXForwardedForKey = $validatorData[self::VALIDATOR_HTTP_X_FORVARDED_FOR_KEY];
                if ($this->useValidateHttpXForwardedFor()
                    && $sessionValidateHttpXForwardedForKey != $validatorValidateHttpXForwardedForKey ) {
                        return false;
                    }
                    if ($this->useValidateHttpUserAgent()
                        && $sessionData[self::VALIDATOR_HTTP_USER_AGENT_KEY] != $validatorData[self::VALIDATOR_HTTP_USER_AGENT_KEY]
                    ) {
                        $userAgentValidated = $this->getValidateHttpUserAgentSkip();
                        foreach ($userAgentValidated as $agent) {
                            if (preg_match('/' . $agent . '/iu', $validatorData[self::VALIDATOR_HTTP_USER_AGENT_KEY])) {
                                return true;
                            }
                        }
                        return false;
                    }

                    return true;
    }

    /**
     * Retrieve unique user data for validator
     *
     * @return array
     */
    public function getValidatorData()
    {
        $parts = array(
            self::VALIDATOR_REMOTE_ADDR_KEY             => '',
            self::VALIDATOR_HTTP_VIA_KEY                => '',
            self::VALIDATOR_HTTP_X_FORVARDED_FOR_KEY    => '',
            self::VALIDATOR_HTTP_USER_AGENT_KEY         => ''
        );

        // collect ip data
        if (Mage::helper('core/http')->getRemoteAddr()) {
            $parts[self::VALIDATOR_REMOTE_ADDR_KEY] = Mage::helper('core/http')->getRemoteAddr();
        }
        if (isset($_ENV['HTTP_VIA'])) {
            $parts[self::VALIDATOR_HTTP_VIA_KEY] = (string)$_ENV['HTTP_VIA'];
        }
        if (isset($_ENV['HTTP_X_FORWARDED_FOR'])) {
            $parts[self::VALIDATOR_HTTP_X_FORVARDED_FOR_KEY] = (string)$_ENV['HTTP_X_FORWARDED_FOR'];
        }

        // collect user agent data
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $parts[self::VALIDATOR_HTTP_USER_AGENT_KEY] = (string)$_SERVER['HTTP_USER_AGENT'];
        }

        return $parts;
    }

    /**
     * Regenerate session Id
     *
     * @return Mage_Core_Model_Session_Abstract_Varien
     */
    public function regenerateSessionId()
    {
        Mage::registry(self::SESSION)->renewId();
        return $this;
    }
}
