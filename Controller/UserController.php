<?php
/**
 * Created by PhpStorm.
 * User: phoebegl
 * Date: 2016/11/6
 * Time: 15:11
 */
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once 'Model/UserModel.php';
require_once 'Model/FriendModel.php';

$friend = FriendModel::getInstance();
$user = UserModel::getInstance();

$app->get('/login',function (Request $request, Response $response,$args) use($user) {
    return $this->view->render($response, 'login.php');
});

$app->get('/register',function (Request $request, Response $response,$args) use($user) {
    return $this->view->render($response, 'register.php');
});

$app->get('/userinfo',function (Request $request, Response $response,$args) use($user) {
    return $this->view->render($response, 'userinfo.php');
});

$app->get('/user/show',function (Request $request, Response $response,$args) use($user) {
    session_start();
    if (isset($_SESSION['userid'])) {
        $userid = $_SESSION['userid'];
        $temp = json_decode($user->getUserInfo($userid),true);
        $temp['followerNum'] = json_decode($user->getFollowerNum($userid),true)['followerNum'];
        $temp['followingNum'] = json_decode($user->getFollowingNum($userid),true)['followingNum'];
        return json_encode($temp);
    } else {
        return $this->view->render($response, 'login.php');
    }
});

$app->get('/logout',function (Request $request, Response $response,$args) use($user) {
    session_start();
    $_SESSION['user'] = null;
    $_SESSION['userid'] = null;
    return $this->view->render($response, 'login.php');
});

//登录使用的post方法
$app->post('/homepage',function (Request $request, Response $response, $args) use($user){
    session_start();
    $data = $request->getParsedBody();
    $username = filter_var($data['username'], FILTER_SANITIZE_STRING);
    $password = filter_var($data['password'], FILTER_SANITIZE_STRING);
    if(preg_match('/^[\x{4e00}-\x{9fa5}A-Za-z0-9]{1,}$/u',$username) == FALSE){
        $response->getBody()->write("<script>alert('用户名格式不合法!'); history.go(-1);</script>");
        return $response;
    } else {
        $result = $user->login($username,$password);
        if($result == 1) {
            if($username == 'admin') {
                $_SESSION['user'] = null;
                $_SESSION['userid'] = null;
                return $this->view->render($response,'admin.php');
            } else {
                $_SESSION['user'] = $username;
                $_SESSION['userid'] = $user->getUserId($username);
                return $this->view->render($response,'sportdata.php');
            }
        } else {
            $response->getBody()->write("<script>alert('用户名或密码错误!');history.go(-1); </script>");
            return $response;
        }
    }

});

//注册使用的post方法
$app->post('/userinfo',function (Request $request, Response $response, $args) use($user){
    session_start();
    $data = $request->getParsedBody();
    $phone = filter_var($data['phone'], FILTER_SANITIZE_STRING);
    $username = filter_var($data['username'], FILTER_SANITIZE_STRING);
    $password = filter_var($data['password'], FILTER_SANITIZE_STRING);
    if(!preg_match('/^\d{11}$/',$phone)){
        $response->getBody()->write("<script>alert('手机号码格式有误!'); history.go(-1);</script>");
        return $response;
    }
    if(!preg_match('/^[\x{4e00}-\x{9fa5}A-Za-z0-9]{1,}$/u',$username)){
        $response->getBody()->write("<script>alert('用户名格式不合法!'); history.go(-1);</script>");
        return $response;
    }
    $_SESSION['user'] = $username;
    $_SESSION['userid'] = $user->getUserId($username);
    $result = $user->register($username,$phone,$password);
    if($result == 1) {
        $response->getBody()->write("<script>alert('注册成功!');</script>");
        return $this->view->render($response,'userinfo.php');
    } else{
        $response->getBody()->write("<script>alert('信息输入有误!'); history.go(-1);</script>");
        return $response;
    }
});

//修改个人信息使用的post方法
$app->post('/user/info',function (Request $request, Response $response, $args) use($user){
    session_start();
    if (isset($_SESSION['user'])) {

        if($_FILES['file']['error'] > 0) {
            $response->getBody()->write("<script>alert('上传头像失败!');history.go(-1); </script>");
            return $response;
        } else {
            $target = "public/img/upload/" . $_FILES['file']['name'];
            if(move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
                $data = $request->getParsedBody();
                $username = $_SESSION['user'];
                $sex = filter_var($data['sex'], FILTER_SANITIZE_STRING);
                $weight = filter_var($data['weight'], FILTER_SANITIZE_STRING);
                $birth = filter_var($data['birth'], FILTER_SANITIZE_STRING);
                $location = filter_var($data['location'], FILTER_SANITIZE_STRING);
                $interest = filter_var($data['interest'], FILTER_SANITIZE_STRING);
                $slogen = filter_var($data['slogen'], FILTER_SANITIZE_STRING);

                $result = $user->updateUser($username,$target,$sex,$weight,$birth,$location,$interest,$slogen);
                if ($result == 1) {
                    $response->getBody()->write("<script>alert('更新成功!');history.go(-1);</script>");
                } else {
                    $response->getBody()->write("<script>alert('更新失败!');history.go(-1); </script>");
                    return $response;
                }
            }
        }
    } else {
        return $this->view->render($response, 'login.php');
    }
});

//修改密码使用的post方法
$app->post('/user/account',function (Request $request, Response $response, $args) use($user){
    session_start();
    if (isset($_SESSION['user'])) {
        $data = $request->getParsedBody();
        $username = $_SESSION['user'];
        $current = filter_var($data['current_pwd'], FILTER_SANITIZE_STRING);
        $new = filter_var($data['new_pwd'], FILTER_SANITIZE_STRING);
        $repeat = filter_var($data['repeat_pwd'], FILTER_SANITIZE_STRING);

        if($new != $repeat) {
            $response->getBody()->write("<script>alert('两次新密码输入不一致!');history.go(-1); </script>");
            return $response;
        }
        $temp = $user->login($username,$current);
        if($temp == 1) {
            $result = $user->updatePassword($username,$new);
            if ($result == 1) {
                $response->getBody()->write("<script>alert('更新成功!');history.go(-1);</script>");
            } else {
                $response->getBody()->write("<script>alert('更新失败!');history.go(-1); </script>");
                return $response;
            }
        } else {
            $response->getBody()->write("<script>alert('原密码输入错误!');history.go(-1); </script>");
            return $response;
        }
    } else {
        return $this->view->render($response, 'login.php');
    }
});

$app->get('/searchre',function (Request $request, Response $response,$args) use($user) {
    session_start();
    if (isset($_SESSION['keyword'])) {
        $temp = json_decode($user->searchUser($_SESSION['keyword']),true);
        for($i=0;$i<count($temp);$i++) {
            if($user->isFriend($_SESSION['userid'],$temp[$i]['userid']) == 1) {
                $temp[$i]['isFriend'] = 1;
            } else {
                $temp[$i]['isFriend'] = 0;
            }
        }
        return json_encode($temp);
    } else {
        return $this->view->render($response, 'login.php');
    }
});

$app->get('/search',function (Request $request, Response $response,$args) use($user) {
    $data = $request->getParsedBody();
    session_start();
    $keyword = filter_var($data['inputsearch'], FILTER_SANITIZE_STRING);
    $_SESSION['keyword'] = $keyword;
    return $this->view->render($response, 'search.php');
});

$app->get('/friend/show',function (Request $request, Response $response,$args) use($user,$friend) {
    session_start();
    $userid = $_SESSION['sportuserid'];
    $temp = json_decode($user->getUserInfo($userid),true);
    $temp['followerNum'] = json_decode($user->getFollowerNum($userid),true)['followerNum'];
    $temp['followingNum'] = json_decode($user->getFollowingNum($userid),true)['followingNum'];
    if($friend->isFriend($_SESSION['userid'],$_SESSION['sportuserid'])) {
        $temp['isFriend'] = 1;
    } else {
        $temp['isFriend'] = 0;
    }
    return json_encode($temp);
});

$app->get('/homepage',function (Request $request, Response $response, $args) use($user){
    session_start();
    if(isset($_SESSION['userid'])) {
        return $this->view->render($response, 'sportdata.php');
    } else {
        return $this->view->render($response, 'admin.php');
    }

});