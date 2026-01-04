<?php
// test_redis_send.php
if(isset($_POST['msg'])) {
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379); 
        $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903'); 
        $redis->publish('redesplit:channel', $_POST['msg']);
    } catch (Exception $e) {}
}
?>