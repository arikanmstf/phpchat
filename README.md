# phpchat
Phpchat is a small php-javascript chat appliication 

## how to use
* open ./server/_inc/api.php file and edit 'DATAFOLDER' constant name as server data folder.
* you can see example of a data folder on ./server/data location.
* on data folder userList.json file must be exists .
* edit ./client/_inc/api.php protected $users array on Session class , same as userList.json
Javascript
```js
var opts = {
    wsUri  : "ws://192.168.1.26:9000/phpchat/server/server.php",
    USERID : USERID,
    USERNAME:USERNAME,
    maximumMessageNo:30
    //liveRefreshInterval:1000,
    //reConnectInterval : 3000,
    //openedMessageNum  : 10
    //maximumMessageNo  : 40
},
ms = new chatAppController(opts);
```
* you can now open and login.

## external libraries
this project is using following external libraries;

* jquery-typing - https://github.com/narfdotpl/jquery-typing
* jquery
* reconnecting-websocket https://github.com/joewalnes/reconnecting-websocket
