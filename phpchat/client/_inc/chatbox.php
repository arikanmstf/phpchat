<script type="text/javascript">
    window.USERNAME = '<?= $api->Session->name ?>';
    window.USERID = '<?= $api->Session->user_id ?>';
</script>
<script type="text/javascript" src="js/lib/reconnecting-websocket.min.js"></script>
<script type="text/javascript" src="js/lib/jquery.typing-0.2.0.min.js"></script>
<script type="text/javascript" src="js/api.js"></script>
<script type="text/javascript">
var opts = {
    wsUri  : "ws://192.168.2.20:9000/chat2/server/server.php",
    USERID : USERID,
    USERNAME:USERNAME,
    maximumMessageNo:30
    //liveRefreshInterval:1000,
    //reConnectInterval : 3000,
    //openedMessageNum  : 10
    //maximumMessageNo  : 40
},
ms = new chatAppController(opts);

</script>


<div class="chat-container" id="chat_app_1">
    
</div>
<div class="chat-userlist-container" for="chat_app_1">


    <nav class="navbar navbar-chatuserlist">
        <div class="container-fluid">
            <div class="navbar-header">
                <button class="navbar-toggle toggle-userlist" data-toggle="collapse" data-target="#chat_userlist1" aria-expanded="false">
                    <span class="glyphicon glyphicon-comment"></span>
                    <!--<span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>-->
                </button>
            </div>
        </div>
    </nav>
    
    <ul class="chat-userlist" id="chat_userlist1">
        <li style="padding:10px">
            <a href="./index.php?logout=true" ><span class="glyphicon glyphicon-log-out">Logout</span></a>
        </li>
        <?php
            for($i=0;$i<1;$i++){ foreach ($api->Session->chatFriends as  $u) :            
        ?>
       
        <li data-id="<?= $u['user_id']?>" class="chat-user">
            
            <a >
                <div class="chat-user-list-child-container">
                    <div class="chat-user-list-image-container">
                        <img width="32" height="32" src="./img/profile_male.png">
                    </div>
                    <div class="chat-user-list-status-container">
                        <span class="glyphicon offline"></span>
                    </div>
                    <div class="chat-user-list-name-container"><?= $u['user_name'] ?></div>
                </div>
                
            </a>
            
        </li>

        <?php
            endforeach;}
        ?>
       <div class="chat-userlist-inactive">
            <center>
                <img src="./img/preloader.gif"><br>
                 <span> Connection problem. Trying to reconnect ...</span>
            </center>

        </div>

    </ul>
</div>