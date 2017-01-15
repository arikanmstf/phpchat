Date.now = function() { return Math.floor( new Date().getTime() /1000 ); }
var chatAppWebSocket = function (opts) {
	var _self = this , $parent = opts.parentObj;
	_self.wsUri = opts.wsUri;
	_self.chatUserID = opts.USERID;
	_self.liveRefreshInterval = opts.liveRefreshInterval;

	_self.connect = function(){
		_self.websocket = new ReconnectingWebSocket(_self.wsUri, null, {debug: false, reconnectInterval: opts.reConnectInterval});
		_self.init();	
	}

	

	_self.init = function(){

		/* WebSocket Events *************/
		_self.on = {

			open : function(callback){
				_self.websocket.onopen = function(ev) {
					_self.log("onopen()");

					_self.onlineInterval = setInterval(function(){
						_self.refreshOnlineList();
					},_self.liveRefreshInterval);
					_self.hideInactive();
					if(typeof(callback) == "function")callback(ev);
				}
			},
			message :function(callback){
				_self.websocket.onmessage = function(ev) {
					_self.log("onmessage()");

					var msg = JSON.parse(ev.data);

					var processMessage = function(msg){
						_self.log("processMessage()");
						switch(msg.type){
							case 'system':
								switch(msg.message){
									case 'tell_me_your_information' : 
										_self.sendMyInformation();
										break;
									case 'msg_read_success' : 
										$parent.msgReadSuccess(msg);
										break;
									case 'msg_delivered' : 
										$parent.addDeliveredMessage(msg);
										break;
									case 'type_started' :
										$parent.makeTypeStart(msg);
										break;
									case 'type_ended' :
										$parent.makeTypeEnd(msg);
										break;
									case 'msg_send_success' :
										$parent.addSendMessage(msg);
										break;
									default :
										break;
								}
								break;
							case 'chat_ready':
								break;
				
						}
					}
					processMessage(msg);
					if(typeof(callback) == "function")callback(ev);
				};
			},
			error : function(callback){
				_self.websocket.onerror	= function(ev){
					_self.log('onerror()');
					if(typeof(callback) == "function")callback(ev);
				}; 
			},
			close : function(callback){
				_self.websocket.onclose = function(ev){
					_self.log('onclose()');
					_self.showInactive();
					clearInterval(_self.onlineInterval);
					if(typeof(callback) == "function")callback(ev);
				};
			}
		}
		/******* endof WebSocket Events */

	}
	
	_self.hideInactive = function(){
		$('.chat-userlist-inactive').hide();
	}
	_self.showInactive = function(){
		$('.chat-userlist-inactive').show();
	}

	/* communication */
	_self.markMessageRead = function(msg){
		var answer = {
			type:'system',
			message: 'mark_message_read',
			message_id: msg.message_id,
			sendTime : Date.now(),
			user_id:_self.chatUserID
		}

		_self.websocket.send(JSON.stringify(answer));
	}
	_self.sendMyInformation = function(){
		var answer = {
			type:'system',
			message: 'my_information',
			user_name: $parent.USERNAME,
			sendTime : Date.now(),
			user_id:_self.chatUserID
		}
		_self.websocket.send(JSON.stringify(answer));
	}
	_self.refreshOnlineList = function(){
		var msg =  {
			type : 'system',
			message : 'tell_me_online_users',
			user_id : _self.chatUserID,
			last_heartbeat : Date.now()
		}
		_self.websocket.send(JSON.stringify(msg));
	}
	_self.sendTypeStart = function(o){
		var msg =  {
			type : 'system',
			message : 'type_start',
			user_id : _self.chatUserID,
			from : o.from,
			to:o.to
		}
		_self.websocket.send(JSON.stringify(msg));
	}
	_self.sendTypeEnd = function(o){
		var msg =  {
			type : 'system',
			message : 'type_end',
			user_id : _self.chatUserID,
			from : o.from,
			to:o.to
		}
		_self.websocket.send(JSON.stringify(msg));
	}
	
	/* */
	_self.connect();
	

	/* Functions */
	_self.log = function(s){
		//console.log(s);
	}

}
var chatAppChatWindow = function(opts){
	var _self=this,messageBox = opts.messageBox,
	sendBtn = opts.sendBtn, 
	inputBtn = opts.inputBtn,
	unInput = opts.unInput,
	messageBoxLoading = opts.messageBoxLoading,
	$parent = opts.parentObj;
	_self.openedMessageNum = opts.openedMessageNum;
	_self.maximumMessageNo = opts.maximumMessageNo;
	_self.msgJSON = [];
	_self.directMessageID = opts.directMessageID;
	_self.chatUserID = opts.USERID;
	_self.windowID = opts.windowID;
	



	if(!_self.directMessageID)return false;


	/* Messaging ***************/
	_self.getMessage = function(msg){

		_self.msgJSON.push(msg);
		_self.msgJSON = _self.msgJSON.splice(_self.msgJSON.length-_self.maximumMessageNo,_self.maximumMessageNo);

		_self.updateMessages();
		/*if(msg.from == _self.chatUserID )*/
			$parent.ws.markMessageRead(msg);
			$parent.checkDeliveredMessages();

		$(messageBox).scrollTop(
			$(messageBox).prop("scrollHeight")
			);
	}
	
	_self.updateMessages = function(){


		_self.convertToSmileys = function (txt) {
			
			txt =txt.replace(/\:\)/ig,'<span class="emotion icon-smile"></span>');
			txt =txt.replace(/\:D/ig,'<span class="emotion icon-happy"></span>');
			txt =txt.replace(/\:P/ig,'<span class="emotion icon-tongue"></span>');
			txt =txt.replace(/\:\(/ig,'<span class="emotion icon-sad"></span>');
			txt =txt.replace(/\;\)/ig,'<span class="emotion icon-wink"></span>');
			txt =txt.replace(/\:O/ig,'<span class="emotion icon-shocked"></span>');
			txt =txt.replace(/\:S/ig,'<span class="emotion icon-confused"></span>');
			txt =txt.replace(/\:\|/ig,'<span class="emotion icon-neutral"></span>');

			return txt;
		}

		var html = '';
		var calculateSendTime = function(sendTime){
			var diff = Date.now() - sendTime , result = '';
			if(diff < 60){ //saniye
				result = 'Just Now';
			}else if(diff < 3600){ //dakika
				result = Math.floor(diff/60) + ' mins';
			}else if (diff<3600*24) {//saat
				result =  Math.floor(diff/3600) + ' hours';
			}else if(diff<3600*24*7){
				result = Math.floor(diff/ (3600*24) ) + ' days';				
			}else{
				result = 'old';
			}
			return result; 
		}
		var len = 10;
		s = 0;
		if(!(_self.msgJSON.length<=len))s=len;
		

		for (var i =0; i < _self.msgJSON.length; i++) {

			var msg = _self.msgJSON[i],
			umsg = msg.content.replace(/<([^ ])>|<([^ ])/ig,""),
			umsg = _self.convertToSmileys(umsg),
			uname = msg.user_name,
			sendTimeMin = calculateSendTime(msg.sendTime);

			if($parent.isSent(msg.from)){//sent
				uname = 'me';
				var delivered = msg.is_delivered ? ' delivered' : '';
				var sent = msg.is_sent ? ' glyphicon-ok ' : '';
				html += '<div data-msgsta="sent" data-msgid="'+msg.message_id+'" class="row msg_container base_sent"><div class="col-md-10 col-xs-10"><div class="messages msg_sent"><p>'+umsg+'</p><span class="msg_deliver glyphicon '+sent+' '+delivered+'"></span><time datetime="2009-11-13T20:00">'+uname+' • '+sendTimeMin+'</time> </div></div><div class="col-md-2 col-xs-2 avatar"><img src="./img/profile_male.png" class=" img-responsive "></div></div>';
			}
			else{//receive

				html += '<div data-msgsta="received" data-msgid="'+msg.message_id+'"  class="row msg_container base_receive"><div class="col-md-2 col-xs-2 avatar"><img src="./img/profile_male.png" class=" img-responsive "></div><div class="col-md-10 col-xs-10"><div class="messages msg_receive"><p>'+umsg+'</p><time datetime="2009-11-13T20:00">'+uname+' • '+sendTimeMin+'</time></div></div></div> ';
			}
		};
		html += '<div class="user-typing" id="userTyping_'+_self.windowID+'"><img src="./img/typing.gif" width="40"/> <span>Typing...</span></div> '

		$(messageBox).html(html);
		_self.hideVeryOldMessages();


	}
	_self.sendMessage = function(){
		var mymessage = $(inputBtn).val(); 
		
		if($parent.USERNAME == ""){
			alert("Enter your Name please!");
			return;
		}
		if(mymessage == ""){
			alert("Enter Some message Please!");
			return;
		}
		

		var msg = {
			message_id : "",
			from:_self.chatUserID,
			to:_self.directMessageID,
			is_read :false,
			is_delivered : false,
			is_sent : false,
			sendTime : Date.now(),
			windowID :_self.windowID,
			content: mymessage,
			user_name:$parent.USERNAME,
			type:'chat'
		};
		$parent.sendMessage(msg);
		//	
		$(inputBtn).val('');
	}

	_self.hideVeryOldMessages = function(num){

		if(typeof(num)=='undefined')num = _self.openedMessageNum;
		else num = _self.openedMessageNum += num;
		$(messageBoxLoading).show();
		
		var msgs  = $(messageBox).find('.msg_container');
		for (var i = 0; i < msgs.length- 1; i++) {
				$(msgs[i]).show();
			};

		if(msgs.length>num){

			if(!$('#chatShowMore_'+_self.windowID).length >0){
				var html = '<div class="chat-show-more" id="chatShowMore_'+_self.windowID+'"><span>Show more</span></div>';
				$(messageBox).prepend(html);
				$('#chatShowMore_'+_self.windowID).click( function (e) {
					
					_self.hideVeryOldMessages(10);
				});
			} 

			
			for (var i = 0; i < msgs.length- num; i++) {
				$(msgs[i]).hide();
			};
		}else{
			$('#chatShowMore_'+_self.windowID).remove();
		}
		$(messageBoxLoading).hide();
	}
	_self.showTyping = function(){
		$('#userTyping_'+_self.windowID).css('visibility','visible');
	}
	_self.hideTyping = function(){
		$('#userTyping_'+_self.windowID).css('visibility','hidden');
	}
	
	/******* endof Messaging **/

	/* Jquery Functions *********/
	$(sendBtn).click(function(){
			_self.sendMessage();
	});
	$(inputBtn).keyup(function(e){
	    if(e.keyCode == 13){
	        $(sendBtn).trigger('click');
	    }
	    else if(e.keyCode == 27){
	        $(this).parent().parent().parent().find('.icon_close').trigger('click');
	    }
    });

	$(inputBtn).typing({
		start : function(e,$elem){
			$parent.ws.sendTypeStart({from:_self.chatUserID,
			to:_self.directMessageID});
		},
		stop : function(e,$elem){
			$parent.ws.sendTypeEnd({from:_self.chatUserID,
			to:_self.directMessageID});
		},
		delay:1000
	});


    $('#chat_window_'+_self.windowID).bind('keydown',function(e){
    	if(e.keyCode == 13){
	        //$(sendBtn).trigger('click');
	    }
	    else if(e.keyCode == 27){
	        $(this).find('.icon_close').trigger('click');
	    }
    });
	/* endof Jquery Functions ***/

	/* Functions */
	_self.log = function(s){
		console.log(s);
	}

}
var chatAppController = function(opts){
	var _self =this;
	_self.documentTitle = document.title;
	_self.titleBlinkInterval = false;
	_self.USERNAME = opts.USERNAME;

	if(typeof(opts.reConnectInterval) == 'undefined')opts.reConnectInterval=3000;
	if(typeof(opts.liveRefreshInterval) == 'undefined')opts.liveRefreshInterval=1000;
	if(typeof(opts.openedMessageNum) == 'undefined')_self.openedMessageNum=10;
	else _self.openedMessageNum = opts.openedMessageNum;


	if(typeof(opts.maximumMessageNo) == 'undefined')_self.maximumMessageNo=40;
	else _self.maximumMessageNo = opts.maximumMessageNo;

	if(typeof(opts.USERID) == 'undefined')throw new Error('you must specify a USERID to start chatAppController.');
	if(typeof(opts.wsUri) == 'undefined')throw new Error('you must specify a wsUri to start chatAppController.');


	/* WebSocket *************/
	var opts = {
		wsUri : opts.wsUri,
		USERID : opts.USERID,
		parentObj : _self,
		liveRefreshInterval:opts.liveRefreshInterval,
		reConnectInterval : opts.reConnectInterval
	}
	_self.chatUserID = opts.USERID;
	_self.ws = new chatAppWebSocket(opts);
	$(window).on('beforeunload', function(){
	    _self.ws.websocket.close();
	});
	$(window).unload(function(){
	    _self.ws.websocket.close();
	});

	_self.ws.on.open(function(){
		 
	});
	_self.ws.on.message(function(ev){
		var msg = JSON.parse(ev.data);
		
		var processMessage = function(msg){
			switch(msg.type){
				case 'private_chat':
						if(msg.is_read){ //okunmus mesaj

						}else{//okunmamıs mesaj
							
							_self.addUnReadMessage(msg);

						}
						if(_self.isSent(msg.from)){
							
							for (var i = 0; i < _self.chatWindows.length; i++) {
								if(_self.chatWindows[i].windowID == msg.windowID){
									_self.chatWindows[i].getMessage(msg);
								}
							};
						}else{
							var found = false;


							for (var i = 0; i < _self.chatWindows.length; i++) {
								if(_self.chatWindows[i].directMessageID == msg.from){
									found = true;
									/* pencere açık */
									_self.chatWindows[i].getMessage(msg);
								}
							};

							
						}
				
						_self.addOldMessages(msg);					
				break;
			}
		}
		
		_self.updateOnlineList(msg.online_users);
		processMessage(msg);
	});
	_self.ws.on.error(function(){
	});
	_self.ws.on.close(function(){
		

	});

	
	

	/* endof WebSocket **********/

	/* ChatWindow ***************/
	_self.chatWindows = [];
	_self.oldMessages = [];

	_self.createChatWindow = function(opts){
		var chatWindow =  new chatAppChatWindow(opts);
		_self.heightFix();
		_self.chatWindows.push(chatWindow);
		_self.getOldMessages(chatWindow);
		return {
				success : function (callback) {
					callback();
				}
		}
	}
	_self.addOldMessages= function(msg){
		_self.oldMessages.push(msg);
	}
	_self.getOldMessages = function(chatWindow){
		
		for (var i = 0; i < _self.oldMessages.length; i++) {

			if(_self.oldMessages[i].from == chatWindow.directMessageID || _self.oldMessages[i].to == chatWindow.directMessageID){
				chatWindow.getMessage(_self.oldMessages[i]);
				_self.ws.markMessageRead(_self.oldMessages[i]);
			}
			
			
		};
	}

	_self.msgReadSuccess = function(msg){
		var new_arr = [];
		
		for (var i = 0; i < _self.unreadMessages.length; i++) {
			if(_self.unreadMessages[i].message_id != msg.message_id){

				new_arr.push(_self.unreadMessages[i]);
			}
		};
		_self.unreadMessages = new_arr;
		_self.checkUnReadMessages();
	}
	

	//
	_self.unreadMessages = [];
	_self.addUnReadMessage = function(msg){
		_self.unreadMessages.push(msg);
		_self.checkUnReadMessages();
	}
	_self.checkUnReadMessages = function(){

		var buffer = _self.unreadMessages;
		//_self.unreadMessages = [];
		$('.chat-user').each(function () {

			$(this).find('div.chat-user-list-image-container').attr('data-messages',0);

			var found = 0;

			for (var i = 0; i < buffer.length; i++) {
				if($(this).attr('data-id')==buffer[i].from){
					found++;
				}
			}
			_self.addMessageNotification($(this).find('div.chat-user-list-image-container'),found);
		});

		if(_self.unreadMessages.length > 0){
			if(!_self.titleBlinkInterval){
				_self.titleBlinkInterval = setInterval(function(){
					if(document.title == _self.documentTitle){
						document.title = 'You have new messages.';
					}else{
						document.title = _self.documentTitle;
					}
				},1000)
			}
		}else{
			document.title = _self.documentTitle;
			clearInterval(_self.titleBlinkInterval);
			_self.titleBlinkInterval=false;
		}



	}
	_self.addMessageNotification = function($elem,num){

		if(num>0){
			$elem.attr('data-messages',num);
		}else{
			$elem.attr('data-messages','');
		}
		
	}

	//
	_self.sendMessages = [];
	_self.addSendMessage = function(msg){
		_self.sendMessages.push(msg);
		_self.checkSendMessages();
	}
	_self.checkSendMessages = function(){
		$('.base_sent').each(function(){
			var elem = $(this);
			for (var i = 0; i < _self.sendMessages.length; i++) {
				
				if(elem.attr('data-msgsta') == 'sent' && elem.attr('data-msgid') == _self.sendMessages[i].message_id ){
					elem.find('span.msg_deliver').addClass('glyphicon-ok');
				}
			};
			

		})
	}


	//
	_self.deliveredMessages = [];

	_self.addDeliveredMessage = function(msg){
		_self.deliveredMessages.push(msg);
		_self.checkDeliveredMessages();
	}
	_self.checkDeliveredMessages = function(){
		$('.base_sent').each(function(){
			var elem = $(this);
			for (var i = 0; i < _self.deliveredMessages.length; i++) {
				
				if(elem.attr('data-msgsta') == 'sent' && elem.attr('data-msgid') == _self.deliveredMessages[i].message_id ){
					elem.find('span.msg_deliver').addClass('glyphicon');
					//elem.find('span.msg_deliver').addClass('glyphicon-ok');
					elem.find('span.msg_deliver').addClass('delivered');
				}
			};
			

		})
	}

	_self.makeTypeStart = function(msg){
		//find opened window
		for (var i = 0; i < _self.chatWindows.length; i++) {
			if(_self.chatWindows[i].directMessageID == msg.from){
				_self.chatWindows[i].showTyping();
			}
		};
	}
	_self.makeTypeEnd = function(msg){
		//find opened window
		for (var i = 0; i < _self.chatWindows.length; i++) {
			if(_self.chatWindows[i].directMessageID == msg.from){
				_self.chatWindows[i].hideTyping();
			}
		};
	}

	/* endof ChatWindow *********/

	_self.sendMessage = function(msg){
		_self.ws.websocket.send(JSON.stringify(msg));
	}
	
	/* Jquery Functions ***********/
	_self.updateOnlineList = function(os){

		if(typeof(os) == "undefined")return false;
		var user_li = $('li.chat-user');
		user_li.each(function(){
			$(this).removeClass('online');
			$(this).addClass('offline');


		});
		for (var i = 0; i < os.length; i++) {
			user_li.each(function(){
				if($(this).attr('data-id') == 	os[i].user_id ){
					$(this).removeClass('offline');
					$(this).addClass('online');
					return;
				}
			});
		};

		//chat windows
		var foo = $(".panel-title span.glyphicon-comment");
		foo.each(function(){
			$(this).removeClass('span-green');
			$(this).addClass('span-red');
		});

		for (var i = 0; i < os.length; i++) {
			foo.each(function(){
				if($(this).attr('data-id') == 	os[i].user_id ){
					$(this).removeClass('span-red');
					$(this).addClass('span-green');
					return;
				}
			});
		};

	}


	var id_arr = [];
	function generateRandomID(){

		var id = parseInt(Math.random() * 100000);
		if( id_arr.indexOf(id) > 0 ){
			return generateRandomID();
		}else{
			id_arr.push(id);
			return id;var h = $(window).height();
		}
	}


	$(document).on('click', '.panel-heading span.icon_minim', function (e) {
	    var $this = $(this);
	    if (!$this.hasClass('panel-collapsed')) {
	        $this.parents('.panel').find('.panel-body').hide();
	        $this.addClass('panel-collapsed');
	        $this.removeClass('glyphicon-chevron-down').addClass('glyphicon-chevron-up');

	         
	    } else {
	        $this.parents('.panel').find('.panel-body').show();
	        $this.removeClass('panel-collapsed');
	        $this.removeClass('glyphicon-chevron-up').addClass('glyphicon-chevron-down');
	    }

		_self.heightFix()
	       
	});
	
	$(document).on('focus', '.panel-footer input.chat_input', function (e) {
	    var $this = $(this);
	    var minimwin = $(this).parent().parent().parent().find('.minim_chat_window');
	    if ($(minimwin).hasClass('panel-collapsed')) {
	        $this.parents('.panel').find('.panel-body').show();
	        $(minimwin).removeClass('panel-collapsed');
	        $(minimwin).removeClass('glyphicon-chevron-up').addClass('glyphicon-chevron-down');
	    }
	    _self.heightFix()
	});
	$(document).on('click', '.icon_close', function (e) {
	    var id = $(this).attr("for");
	    _temp = id.split('_');
	    windowID = parseInt (_temp[_temp.length-1])
	    for (var i = 0; i < _self.chatWindows.length; i++) {
	    	if(_self.chatWindows[i].windowID == windowID){
	    		_self.chatWindows.splice(i,1);
	    	}
	    };
	    $('.chat-user').each(function () {
	    	if($(this).attr('opened') == windowID)$(this).attr('opened',false);
	    });

	   	var parentElem = $(this).parent().parent().parent()
	    .parent().parent().parent().parent(),
	    _id = parentElem.attr('id');
	    
		parentElem.parent().find('.chat-userlist-container').removeClass('not-show');
	    
	    
	    $( "#"+_id ).removeClass('full-width');
	    $( "#"+id ).remove();


	});

	$(document).on('click', '.chat-user', function (e) {


		if($(this).hasClass('offline')){
			//alert('user is offline')
			//return false;
		}

		

		var f = false,id = $(this).parent().parent().attr("for");
		for (var i = 0; i < _self.chatWindows.length; i++) {
			if(_self.chatWindows[i].directMessageID == $(this).attr('data-id')){
				f = _self.chatWindows[i];
				break;
			}
		};
		if(f){
			$('#inputBtn_'+f.windowID).focus();
			$(this).parent().parent().addClass('not-show');
			$("#"+id).addClass('full-width');
			return false ;
		}
		
		var windowID =  generateRandomID(),
		chatTitle = $(this).find('div.chat-user-list-name-container').text(),
		directMessageID = $(this).attr('data-id'),
		opened = $(this).attr('opened');
		if(opened>0){
			$('#inputBtn_'+opened).focus();
			return;
		}else{
			$('.chat-user').each(function(){$(this).attr('opened',false)});
			$(this).attr("opened",windowID);
		}
		$(this).parent().parent().addClass('not-show');


		var html = '<div class="row chat-window col-xs-5 col-md-3" id="chat_window_'+windowID+'" tabindex="1" ><div class="col-xs-12 col-md-12 chat-panel"><div class="panel panel-default" ><div class="chat-userlist-inactive"><center><img src="./img/preloader.gif"><br><span> Connection problem. Trying to reconnect ...</span></center></div><div class="panel-heading top-bar"><div class="col-md-8 col-xs-8"><div class="panel-title"> <span class="glyphicon glyphicon-comment" data-id="'+directMessageID+'"></span><span>'+chatTitle+'</span></div></div><div class="col-md-4 col-xs-4" style="text-align: right;"><a href="#"><span class="glyphicon glyphicon-chevron-down icon_minim minim_chat_window"></span></a><a href="#"><span class="glyphicon glyphicon-remove icon_close" for="chat_window_'+windowID+'"></span></a></div></div><div class="chat-loading-messages" id="messageBoxLoading_'+windowID+'"><center><img src="./img/preloader.gif"></center></div><div class="panel-body msg_container_base" id="messageBox_'+windowID+'"></div><div class="panel-footer"><div class="input-group"><input id="inputBtn_'+windowID+'" type="text" class="form-control input-sm chat_input" placeholder="Write your message here..." /><input id="unInput_'+windowID+'" type="hidden" value="'+_self.USERNAME+'" /><span class="input-group-btn"><button class="btn btn-primary btn-sm" id="sendBtn_'+windowID+'">Send</button></span></div></div></div></div></div>';

		$( "#"+id ).append(html);
		$("#"+id).addClass('full-width');


		$('#inputBtn_'+windowID).focus();
		var opts = {
			USERID : _self.chatUserID,
			messageBox: '#messageBox_'+windowID,
			messageBoxLoading: '#messageBoxLoading_'+windowID,
			sendBtn : '#sendBtn_'+windowID,
			inputBtn : '#inputBtn_'+windowID,
			unInput : '#unInput_'+windowID,
			directMessageID : directMessageID,
			windowID : windowID,
			parentObj : _self,
			openedMessageNum:_self.openedMessageNum,
			maximumMessageNo:_self.maximumMessageNo
		}

		var $elem = $(this);

		_self.createChatWindow(opts).success(function(){
			//_self.unreadMessagesReaded($elem.find('div.chat-user-list-image-container'));
		});
		
     	
	});

	$(document).on('click','.toggle-userlist',function(){
		var target = $(this).attr('data-target');
		$(target).toggle();
	});
	/****** endof Jquery Functions*/

	_self.isSent = function(chatUserID){
		return chatUserID == _self.chatUserID;
	}
	_self.heightFix = function () {
		$('.panel.panel-default').each(function () {
				var h = - $(this).height() +1;
				
				$(this).css('marginTop',h+'px');

				var w = $(window).width() - $('.chat-userlist-container').width();
				$(this).parent().parent().parent().css('width',w+'px');

				var height = document.documentElement.clientHeight - 51 -43;
				$(this).find('.msg_container_base').css('height',height+'px');
		})
	}
	window.onresize = function(){
		_self.heightFix();
	}
	/* Functions */
	_self.log = function(s){
		console.log(s);
	}
}