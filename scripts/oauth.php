<?php

if (!defined('IN_FS')) {
    die('Do not access this file directly.');
}

$providers = array(
    'github' => function() use ($fsconf) {
        if (empty($fsconf['oauth']['github_secret']) ||
            empty($fsconf['oauth']['github_id'])     ||
            empty($fsconf['oauth']['github_redirect'])) {

            throw new Exception('Config error make sure the github_* variables are set.');
        }
        return new GithubProvider(array(
            'clientId'     =>  $fsconf['oauth']['github_id'],
            'clientSecret' =>  $fsconf['oauth']['github_secret'],
            'redirectUri'  =>  $fsconf['oauth']['github_redirect'],
            'scopes'       => array('user:email')
        ));
    },
    'google' => function() use ($fsconf) {
        if (empty($fsconf['oauth']['google_secret']) ||
            empty($fsconf['oauth']['google_id'])     ||
            empty($fsconf['oauth']['google_redirect'])) {

            throw new Exception('Config error make sure the google_* variables are set.');
        }
        return new League\OAuth2\Client\Provider\Google(array(
            'clientId'     =>  $fsconf['oauth']['google_id'],
            'clientSecret' =>  $fsconf['oauth']['google_secret'],
            'redirectUri'  =>  $fsconf['oauth']['google_redirect'],
            'scopes'       => array('email', 'profile')
        ));
    },
    'facebook' => function() use ($fsconf) {
        if (empty($fsconf['oauth']['facebook_secret']) ||
            empty($fsconf['oauth']['facebook_id'])     ||
            empty($fsconf['oauth']['facebook_redirect'])) {

            throw new Exception('Config error make sure the facebook_* variables are set.');
        }
        return new League\OAuth2\Client\Provider\Facebook(array(
            'clientId'     =>  $fsconf['oauth']['facebook_id'],
            'clientSecret' =>  $fsconf['oauth']['facebook_secret'],
            'redirectUri'  =>  $fsconf['oauth']['facebook_redirect'],
        ));
    },
    'microsoft' => function() use ($fsconf) {
        if (empty($fsconf['oauth']['microsoft_secret']) ||
            empty($fsconf['oauth']['microsoft_id'])     ||
            empty($fsconf['oauth']['microsoft_redirect'])) {

            throw new Exception('Config error make sure the microsoft_* variables are set.');
        }
        return new League\OAuth2\Client\Provider\Microsoft(array(
            'clientId'     =>  $fsconf['oauth']['microsoft_id'],
            'clientSecret' =>  $fsconf['oauth']['microsoft_secret'],
            'redirectUri'  =>  $fsconf['oauth']['microsoft_redirect'],
        ));
    },
    'instagram' => function() use ($fsconf) {
        if (empty($fsconf['oauth']['instagram_secret']) ||
            empty($fsconf['oauth']['instagram_id'])     ||
            empty($fsconf['oauth']['instagram_redirect'])) {

            throw new Exception('Config error make sure the instagram_* variables are set.');
        }
        return new League\OAuth2\Client\Provider\Instagram(array(
            'clientId'     =>  $fsconf['oauth']['instagram_id'],
            'clientSecret' =>  $fsconf['oauth']['instagram_secret'],
            'redirectUri'  =>  $fsconf['oauth']['instagram_redirect'],
        ));
    },
    'eventbrite' => function() use ($fsconf) {
        if (empty($fsconf['oauth']['eventbrite_secret']) ||
            empty($fsconf['oauth']['eventbrite_id'])     ||
            empty($fsconf['oauth']['eventbrite_redirect'])) {

            throw new Exception('Config error make sure the eventbrite_* variables are set.');
        }
        return new League\OAuth2\Client\Provider\Eventbrite(array(
            'clientId'     =>  $fsconf['oauth']['eventbrite_id'],
            'clientSecret' =>  $fsconf['oauth']['eventbrite_secret'],
            'redirectUri'  =>  $fsconf['oauth']['eventbrite_redirect'],
        ));
    },
    'linkedin' => function() use ($fsconf) {
        if (empty($fsconf['oauth']['linkedin_secret']) ||
            empty($fsconf['oauth']['linkedin_id'])     ||
            empty($fsconf['oauth']['linkedin_redirect'])) {

            throw new Exception('Config error make sure the linkedin_* variables are set.');
        }
        return new League\OAuth2\Client\Provider\LinkedIn(array(
            'clientId'     =>  $fsconf['oauth']['linkedin_id'],
            'clientSecret' =>  $fsconf['oauth']['linkedin_secret'],
            'redirectUri'  =>  $fsconf['oauth']['linkedin_redirect'],
        ));
    },
    'vkontakte' => function() use ($fsconf) {
        if (empty($fsconf['oauth']['vkontakte_secret']) ||
            empty($fsconf['oauth']['vkontakte_id'])     ||
            empty($fsconf['oauth']['vkontakte_redirect'])) {

            throw new Exception('Config error make sure the vkontakte_* variables are set.');
        }
        return new League\OAuth2\Client\Provider\Vkontakte(array(
            'clientId'     =>  $fsconf['oauth']['vkontakte_id'],
            'clientSecret' =>  $fsconf['oauth']['vkontakte_secret'],
            'redirectUri'  =>  $fsconf['oauth']['vkontakte_redirect'],
        ));
    },
);

if (! isset($_SESSION['return_to'])) {
    $_SESSION['return_to'] = base64_decode(Get::val('return_to', ''));
    $_SESSION['return_to'] = $_SESSION['return_to'] ?: $baseurl;
}

$provider = isset($_SESSION['oauth_provider']) ? $_SESSION['oauth_provider'] : 'none';
$provider = strtolower(Get::val('provider', $provider));
unset($_SESSION['oauth_provider']);

$active_oauths = explode(' ', $fs->prefs['active_oauths']);
if (!in_array($provider, $active_oauths)) {
    Flyspray::show_error(26);
}

$obj = $providers[$provider]();

if ( ! Get::has('code') && ! Post::has('username')) {
    // get authorization code
    header('Location: '.$obj->getAuthorizationUrl());
    exit;
}

if (isset($_SESSION['oauth_token'])) {
    $token = unserialize($_SESSION['oauth_token']);
    unset($_SESSION['oauth_token']);
} else {
    // Try to get an access token
    try {
        $token = $obj->getAccessToken('authorization_code', array('code' => $_GET['code']));
    } catch (\League\OAuth2\Client\Exception\IDPException $e) {
        throw new Exception($e->getMessage());
    }
}

$user_details = $obj->getUserDetails($token);
$uid          = $user_details->uid;

if (Post::has('username')) {
    $username = Post::val('username');
} else {
    $username = $user_details->nickname;
}

// First time logging in
if (! Flyspray::checkForOauthUser($uid, $provider)) {
    if (! $user_details->email) {
        Flyspray::show_error(27);
    }

    $success = false;

    if ($username) {
        $group_in = $fs->prefs['anon_group'];
        $name     = $user_details->name ?: $username;
        $success  = Backend::create_user($username, null, $name, '', $user_details->email, 0, 0, $group_in, 1, $uid, $provider);
    }

    // username taken or not provided, ask for it
    if (!$success) {
        $_SESSION['oauth_token']    = serialize($token);
        $_SESSION['oauth_provider'] = $provider;
        $page->assign('provider', ucfirst($provider));
        $page->assign('username', $username);
        $page->pushTpl('register.oauth.tpl');
        return;
    }
}

if (($user_id = Flyspray::checkLogin($user_details->email, null, 'oauth')) < 1) {
    Flyspray::show_error(23); // account disabled
}

$user = new User($user_id);

// Set a couple of cookies
$passweirded = crypt($user->infos['user_pass'], $fsconf['general']['cookiesalt']);
Flyspray::setCookie('flyspray_userid', $user->id, 0,null,null,null,true);
Flyspray::setCookie('flyspray_passhash', $passweirded, 0,null,null,null,true);
$_SESSION['SUCCESS'] = L('loginsuccessful');

$return_to = $_SESSION['return_to'];
unset($_SESSION['return_to']);

Flyspray::Redirect($return_to);
?>
