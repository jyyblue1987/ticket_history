<html>
<head>
	<script src="assets/jquery-2.2.4.min.js"></script>
</head>
<body>
<script type="text/javascript">
 var ws = new WebSocket('wss://ws.binaryws.com/websockets/v3?app_id=4736');
 var tot=""; 

ws.onopen = function(evt) {
    //ws.send(JSON.stringify({ticks: 'R_50'}));
     ws.send(JSON.stringify({ticks_history: 'R_100',end: 'latest',count: '500'}));
};

ws.onmessage = function(msg) {
   var data = JSON.parse(msg.data);
   console.log('ticks update: %o', data);
};
//Create WebSocket connection.
//const socket = new WebSocket('ws://localhost:8080');

// Connection opened
ws.addEventListener('open', function (event) {
    ws.send('Hello Server!');
});

// Listen for messages
ws.addEventListener('message', function (event) {
    console.log('Message from server', event.data);

    var data = JSON.parse(event.data);
    if( data.error )
    	return;	

    var request = {};
    request.data = data;
    request.symbol = 'R_100';

    $.post("api.php",
	    {
	        data: request        	        
	    },
	    function(data, status){
	        console.log(data);
	    });
});


</script>

</body>
</html>