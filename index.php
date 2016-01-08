<?php
/**
 * Created by PhpStorm.
 * User: hundredlee
 * Date: 1/7/16
 * Time: 9:01 AM
 */
error_reporting(0);

require 'WeChat.php';

define('MAX_GROUP_NUM',35);

$weChat = new WeChat();

//设置uuid
$weChat->setUuid();

echo $weChat->getQRCode();

echo '<br/>';

sleep(20);

while (true) {

    if ($weChat->waitForLogin() == 200) {

        break;

    }

}

echo '<br/>';

if (!$weChat->login()) {

    echo '登录失败!<br/>';

    return;

} else {

    echo '登录成功!<br/>';

}

if (!$weChat->weChatInitial()) {

    echo '初始化失败!<br/>';

    return;

}

$memberList = array_values($weChat->webwxgetcontact());

$memberCount = count($memberList) - 1;

echo "你的微信里目前有 ".$memberCount." 个好友<br/>";

$groupNumber = ceil($memberCount/MAX_GROUP_NUM);



$chatRoomName = '';
for ($i = 0 ;$i < $groupNumber ;$i++){
    $usernames = array();
    $nicknames = array();

    for($j = 0 ;$j < MAX_GROUP_NUM ;$j++){

        if(($i * MAX_GROUP_NUM + $j) >= $memberCount){
            break;
        }
        $member = $memberList[$i + MAX_GROUP_NUM + $j];
        $usernames[] = $member['UserName'];
        $nicknames[] = $member['NickName'];
    }
    //TODO
    if($chatRoomName == ''){

        $chatRoomName = $weChat->createChatRoom($usernames);

    }else{

        $weChat->addMember($chatRoomName,$usernames);

    }

    $weChat->deleteMember($chatRoomName,$usernames);

}

echo '<br/>当前删除你的好友列表如下:<br/>';

$weChat->getDeleteList();




