<?php

class SV_CanonicalRedirect_XenForo_ControllerPublic_Register extends XFCP_SV_CanonicalRedirect_XenForo_ControllerPublic_Register
{
    protected function _preDispatch($action)
    {
        parent::_preDispatch($action);

        $options = XenForo_Application::getOptions();
        if(!$options->SV_CanonicalRedirection_CloudFlareReg)
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