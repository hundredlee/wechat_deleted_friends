<?php
/**
 * Created by PhpStorm.
 * User: hundredlee
 * Date: 1/7/16
 * Time: 9:05 AM
 */
require_once('httpUtils/Requests.php');

Requests::register_autoloader();

class WeChat
{

    private static $deviceId = 'e000000000000000';

    private static $uuid = 0;

    private static $tip = 0;

    private static $redirect_uri = '';

    private static $base_uri = '';

    private static $skey = '';

    private static $wxsid = '';

    private static $wxuin = '';

    private static $pass_ticket = '';

    private static $baseRequest = array();

    private static $contactList = array();

    private static $my = array();

    private static $cookie = null;

    private static $deleteList = array();

    private static $chatRoomName = '';


    /**
     * @return bool
     */
    public function setUuid()
    {

        $param = array(
            'appid' => 'wx782c26e4c19acffb',
            'fun' => 'new',
            'lang' => 'zh_CN',
            '_' => time()
        );

        $responseData = Requests::post('https://login.weixin.qq.com/jslogin', null, $param);

        $body = $responseData->body;
        preg_match_all('/window.QRLogin.code = (\d+); window.QRLogin.uuid = "(\S+?)"/', $body, $array);

        //status code
        $statusCode = $array[1][0];
        $uuid = $array[2][0];


        if ($statusCode == 200) {

            self::$uuid = $uuid;

            return true;

        }

        return false;

    }

    /**
     * @return string
     */
    public function getQRCode()
    {
        $params = array(
            't' => 'index',
            '_' => time()
        );

        $url = 'https://login.weixin.qq.com/qrcode/' . self::$uuid;

        $responseData = Requests::post($url, null, $params);

        self::$tip = 1;

        $src = 'data:image/gif;base64,' . base64_encode($responseData->body);

        $imgLabel = '<img src="' . $src . '" "width=128px" height="128px">';

        return $imgLabel;

    }

    /**
     * @return int
     */
    public function waitForLogin()
    {
        $url = sprintf('https://login.weixin.qq.com/cgi-bin/mmwebwx-bin/login?tip=%s&uuid=%s&_=%s'
            , self::$tip, self::$uuid, time());

        $responseData = Requests::get($url);

        preg_match('/window.code=(\d+)/', $responseData->body, $statusCodeArray);

        //print_r($statusCodeArray);

        $statusCode = (int)$statusCodeArray[1];

        if ($statusCode == 201) {

            echo '扫描成功,请在手机上面点登录 :)<br/>';
            self::$tip = 0;

        } else if ($statusCode == 200) {

            echo '正在登录,请稍后......';

            preg_match('/window.redirect_uri="(\S+?)"/', $responseData->body, $responseArray);

            self::$redirect_uri = $responseArray[1] . '&fun=new';
            self::$base_uri = substr(self::$redirect_uri, 0, strrpos(self::$redirect_uri, '/'));
            //echo self::$redirect_uri.'<br/>'.self::$base_uri;

        } else if ($statusCode == 408) {

            exit('登录超时!请刷新页面重新扫描二维码......');

        }

        return $statusCode;
    }

    public function login()
    {

        $responseData = Requests::get(self::$redirect_uri, null);

        $xmlData = $responseData->body;

        //<error>
        //<ret>0</ret>
        //<message>OK</message>
        //<skey>@crypt_7dd9baa8_b539032f0b7d2a56385e98018735aa39</skey>
        //<wxsid>1ya64xtGW2Aa7rmS</wxsid>
        //<wxuin>2432628783</wxuin>
        //<pass_ticket>njePr%2BqGGxpoiuX%2BBqnolnmUwwJar1YQBcBhHDowzLh1NWsev1%2BMXSWQtoXZBo7p</pass_ticket>
        //<isgrayscale>1</isgrayscale>
        //</error>

        $xml = simplexml_load_string($xmlData);

        $skeyArray = (array)($xml->skey);
        $wxsidArray = (array)($xml->wxsid);
        $pass_ticket = $xml->pass_ticket;

        self::$skey = $skeyArray[0];
        self::$wxsid = $wxsidArray[0];
        self::$wxuin = $xml->wxuin;
        self::$pass_ticket = $pass_ticket;

        if (self::$skey == '' && self::$skey == ''
            && self::$wxuin == '' && self::$pass_ticket == ''
        ) {

            return fasle;

        }

        self::$cookie = $responseData->cookies;

        self::$baseRequest['Uin'] = (int)self::$wxuin;
        self::$baseRequest['Sid'] = self::$wxsid;
        self::$baseRequest['Skey'] = self::$skey;
        self::$baseRequest['DeviceId'] = self::$deviceId;

        return true;

    }

    public function weChatInitial()
    {

        //print_r(self::$baseRequest);


        $url = sprintf(self::$base_uri . '/webwxinit?pass_ticket=%s&skey=%s&r=%s', self::$pass_ticket, self::$skey, time());

        $params = array(
            'BaseRequest' => self::$baseRequest
        );

        $responseData = Requests::post($url,
            array(
                'ContentType' => 'application/json; charset=UTF-8',
            ),
            json_encode($params));


        $dictionary = json_decode($responseData->body, 1);

        //print_r($dictionary);

        self::$contactList = $dictionary['ContactList'];
        self::$my = $dictionary['User'];

        $errorMsg = $dictionary['BaseResponse']['ErrMsg'];

        if (strlen($errorMsg) > 0) {
            echo $errorMsg;
        }

        $ret = $dictionary['BaseResponse']['Ret'];

        if ($ret != 0) {

            return fasle;

        }
        return true;

    }

    public function webwxgetcontact()
    {

        $url = sprintf(self::$base_uri . '/webwxgetcontact?pass_ticket=%s&skey=%s&r=%s', self::$pass_ticket, (self::$skey), time());

        $responseData = Requests::post($url, array('ContentType' => 'application/json; charset=UTF-8'), array(), array('cookies' => self::$cookie));

        $dictionary = json_decode($responseData->body, 1);

        $memberList = $dictionary['MemberList'];

        $specialUsers = array("newsapp", "fmessage", "filehelper", "weibo", "qqmail", "tmessage", "qmessage", "qqsync", "floatbottle", "lbsapp", "shakeapp", "medianote", "qqfriend", "readerapp", "blogapp", "facebookapp", "masssendapp", "meishiapp", "feedsapp", "voip", "blogappweixin", "weixin", "brandsessionholder", "weixinreminder", "wxid_novlwrv3lqwv11", "gh_22b87fa7cb3c", "officialaccounts", "notification_messages", "wxitil", "userexperience_alarm");

        foreach ($memberList as $key => $value) {

            if ((trim($value['VerifyFlag']) & 8) != 0) {

                unset($memberList[$key]);

            }

            if (in_array(trim($value['UserName']), $specialUsers)) {

                unset($memberList[$key]);

            }

            if (trim($value['UserName']) == self::$my['UserName']) {

                unset($memberList[$key]);

            }

            if (strpos(trim($value['UserName']), '@@') !== false) {

                unset($memberList[$key]);

            }

        }

        //print_r($memberList);

        return $memberList;
    }

    public function createChatRoom($usernames = array())
    {
        $usernamesList = array();
        foreach ($usernames as $key => $value) {

            unset($usernames[$key]);

            $usernamesList[]['UserName'] = $value;
        }

        $url = sprintf(self::$base_uri . '/webwxcreatechatroom?pass_ticket=%s&r=%s', self::$pass_ticket, time());

        $params = array(
            'BaseRequest' => self::$baseRequest,
            'MemberCount' => count($usernamesList),
            'MemberList' => $usernamesList,
            'Topic' => ''
        );

        $responseData = Requests::post($url,
            array('ContentType' => 'application/json; charset=UTF-8'),
            json_encode($params),
            array('cookies' => self::$cookie)
        );

        $dictionary = json_decode($responseData->body, 1);

        self::$chatRoomName = $dictionary['ChatRoomName'];

        $memberList = $dictionary['MemberList'];

        foreach ($memberList as $key => $member) {

            if ($member['MemberStatus'] == 4) {

                self::$deleteList[] = $member['UserName'];

            }

        }

        if (strlen($dictionary['BaseResponse']['ErrMsg']) > 0) {

            echo $dictionary['BaseResponse']['ErrMsg'] . '<br/>';

        }

        return self::$chatRoomName;
    }

    public function addMember($chatRoomName, $usernames)
    {

        $url = sprintf(self::$base_uri . '/webwxupdatechatroom?fun=addmember&pass_ticket=%s', self::$pass_ticket);

        $params = array(
            'BaseRequest' => self::$baseRequest,
            'ChatRoomName' => $chatRoomName,
            'AddMemberList' => join(',', $usernames)
        );

        $responseData = Requests::post($url,
            array('ContentType' => 'application/json; charset=UTF-8'),
            json_encode($params),
            array('cookies' => self::$cookie)
        );

        $dictionary = json_decode($responseData->body, 1);

        $memberList = $dictionary['MemberList'];


        foreach ($memberList as $key => $member) {

            if ($member['MemberStatus'] == 4) {

                self::$deleteList[] = $member['UserName'];

            }

        }

        if (strlen($dictionary['BaseResponse']['ErrMsg']) > 0) {

            echo $dictionary['BaseResponse']['ErrMsg'] . '<br/>';

        }

        return true;

    }

    public function deleteMember($chatRoomName, $usernames)
    {

        $url = sprintf(self::$base_uri . '/webwxupdatechatroom?fun=delmember&pass_ticket=%s', self::$pass_ticket);

        $params = array(
            'BaseRequest' => self::$baseRequest,
            'ChatRoomName' => $chatRoomName,
            'DelMemberList' => join(',', $usernames)
        );

        $responseData = Requests::post($url,
            array('ContentType' => 'application/json; charset=UTF-8'),
            json_encode($params),
            array('cookies' => self::$cookie)
        );

        $dictionary = json_decode($responseData->body, 1);

        if (strlen($dictionary['BaseResponse']['ErrMsg']) > 0) {

            echo $dictionary['BaseResponse']['ErrMsg'] . '<br/>';

        }

        $ret = $dictionary['BaseResponse']['Ret'];
        if ($ret != 0) {

            return fasle;

        }

        return true;
    }

    public function getDeleteList()
    {
        return self::$deleteList;
    }
}