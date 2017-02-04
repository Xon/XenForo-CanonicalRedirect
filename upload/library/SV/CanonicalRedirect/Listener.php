<?php

class SV_CanonicalRedirect_Session extends XenForo_Session
{
    public static function isAdminSession($session)
    {
        return isset($session->_config['cacheName']) && $session->_config['cacheName'] == 'session_admin';
    }
}

class SV_CanonicalRedirect_Listener
{
    public static function load_class($class, array &$extend)
    {
        $extend[] = 'SV_CanonicalRedirect_'.$class;
    }

    public static function visitor_setup(XenForo_Visitor &$visitor)
    {
        if (!XenForo_Application::isRegistered('session'))
        {
            return;
        }
        // do not redirect admincp
        $session = XenForo_Application::getSession();
        if (empty($session) || SV_CanonicalRedirect_Session::isAdminSession($session))
        {
            return;
        }

        $canRedirect = false;
        $options = XenForo_Application::getOptions();
        switch($options->SV_CanonicalRedirection)
        {
            case 'all':
                $canRedirect = true;
                break;
            case 'nonadmin':
                $canRedirect = !$visitor['is_admin'];
                break;
            case 'nonmod':
                $canRedirect = !$visitor['is_admin'] && !$visitor['is_mod'];
                break;
            case 'guest':
                $canRedirect = empty($visitor['user_id']);
                break;
            case 'robot':
                $canRedirect = empty($visitor['user_id']) && $session->get('robotId');
                break;
        }
        if (!$canRedirect && $options->SV_CanonicalRedirectionGroups_Blacklist)
        {
            $blacklist = explode(',', $options->SV_CanonicalRedirectionGroups_Blacklist);
            $canRedirect = $blacklist && $visitor->isMemberOf($blacklist);
        }
        if (!$canRedirect)
        {
            return;
        }

        $host = @$_SERVER['HTTP_HOST'];
        if (empty($host))
        {
            // bad config configuration
            return;
        }
        $requestUri = @$_SERVER['REQUEST_URI'];
        $basePath = rtrim(XenForo_Link::convertUriToAbsoluteUri($options->boardUrl, true), '/');
        $boardHost = parse_url($basePath, PHP_URL_HOST);
        if (substr($host, 0, strlen($boardHost)) == $boardHost)
        {
            if ($options->SV_CanonicalRedirection_CloudFlare &&
               (empty($_SERVER['HTTP_CF_RAY']) || empty($_SERVER['HTTP_CF_VISITOR']) || empty($_SERVER['HTTP_CF_CONNECTING_IP'])))
            {
                // on non-cloudflare URL, but not using cloudflare!
                $controllerResponse = new XenForo_ControllerResponse_Error();
                $controllerResponse->errorText = "Must use CloudFlare";
                $controllerResponse->responseCode = 444;
                throw new XenForo_ControllerResponse_Exception($controllerResponse);
            }
            return;
        }

        $controllerResponse = new XenForo_ControllerResponse_Redirect();
        if ($options->SV_CanonicalRedirection_Perm)
        {
            $controllerResponse->redirectType = XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT;
        }
        else
        {
            $controllerResponse->redirectType = XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL;
        }
        $controllerResponse->redirectTarget = $basePath . $requestUri;
        throw new XenForo_ControllerResponse_Exception($controllerResponse);
    }
}