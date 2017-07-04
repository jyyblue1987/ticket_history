<?php
require_once("vendor/autoload.php");

$loop = \React\EventLoop\Factory::create();

$logger = new \Zend\Log\Logger();
$writer = new Zend\Log\Writer\Stream("php://output");
$logger->addWriter($writer);

$client = new \Devristo\Phpws\Client\WebSocket("wss://ws.binaryws.com/websockets/v3?app_id=4736", $loop, $logger);

$client->on("connect", function($headers) use ($client, $logger){
	$logger->notice("connected!");
	// $client->send("{\"ticks\":\"R_100\"}");
	$param = array();
	$param['ticks_history'] = 'R_100';
	$param['end'] = 'latest';
	$param['count'] = '1000';
	$client->send(json_encode($param));
});

$client->on("message", function($message) use ($client, $logger){
	$data = $message->getData();
	$logger->notice("ticks received: ". $data);
	$client->close();

	saveTicketData($data);
});

$client->open();
$loop->run();

function single_total($value) {
	$ret = array_sum(str_split($value));
	if( $value < 10 )
		return $ret;

	return single_total($ret);
}

function total($value) {
	return array_sum(str_split($value));	
}

function gap($last_digit, $array_values) {
	$gap = '';
	for($i = count($array_values) - 1; $i >= 0; $i--) {
		if( $array_values[$i] == $last_digit )
		{
			$gap = count($array_values) - $i;
			break;
		}
	}

	return $gap;
}

function saveTicketData($data) {
	$real_data = json_decode($data, true);

	$history = $real_data;
	$symbol = 'R_100';

	$host = "localhost";
	$dbname = "ticket";
	$username = "root";
	$password = "";    

	// Create connection
	$conn = new mysqli($host, $username, $password, $dbname);

	// Check connection
	if ($conn->connect_error) {
	    die("Connection failed: " . $conn->connect_error);
	}

	// $sql = "DELETE FROM ticket";
	// mysqli_query($conn, $sql);

	// $sql = "ALTER TABLE ticket AUTO_INCREMENT = 1;";
	// mysqli_query($conn, $sql);

	$last_digit_array = [];

	foreach($history['history']['prices'] as $key => $row)
	{
		$value = $row;
		$times = $history['history']['times'][$key];	
		$times = date('Y-m-d H:i:s', $times);

		$last_digit = substr($value, -1);
		$gap = gap($last_digit, $last_digit_array);

		// echo json_encode($last_digit_array);

		$last_digit_array[] = $last_digit;

		$total = total($value);
		$single_total = single_total($total);

		$row_cnt = 0;

		$sql = sprintf("SELECT * FROM ticket WHERE created_at = '%s'", $times);
		if ($result = $conn->query($sql)) {

		    // $row_cnt = $result->num_rows;
		    $row_cnt = mysqli_num_rows($result);

		    /* free result set */
		    $result->free();
		}

		if( $row_cnt < 1 )
		{
			$sql = sprintf("INSERT INTO ticket (symbol, created_at, value, last_digit, total, single_total, gap)	VALUES ('%s', '%s', %f, '%s', '%d', '%d', '%d')", 
			$symbol, $times, $value, $last_digit, $total, $single_total, $gap);

			mysqli_query($conn, $sql);	
		}
		else
		{
			$sql = sprintf("UPDATE ticket SET value = '%s', last_digit = '%s', total = '%s', single_total = '%s', gap = '%s' WHERE created_at = '%s'", 
			$value, $last_digit, $total, $single_total, $gap, $times);

			mysqli_query($conn, $sql);	

			echo 'Updated: ' . $times . '<br>';
		}
		
	}

	$conn->close();
}