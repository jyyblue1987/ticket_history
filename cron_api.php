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

	$sql = "select max(id) as max_id from ticket";
	mysqli_query($conn, $sql);

	$prev_max_block_id = 0;

	if ($result = $conn->query($sql)) {
		while($row = $result->fetch_array())
	  	{
		  	$prev_max_block_id = $row['max_id'];		  	
		  	break;
		}
	}

	$last_digit_array = [];

	foreach($history['history']['prices'] as $key => $row)
	{
		$value = $row;

		$times = $history['history']['times'][$key];	
		$times = date('Y-m-d H:i:s', $times);

		$last_digit = substr($value, -1);
		
		$last_digit_array[] = $last_digit;

		$total = total($value);
		$single_total = single_total($total);

		$row_cnt = 0;

		$sql = sprintf("SELECT id FROM ticket WHERE created_at = '%s'", $times);

		$duplicated_id = 0;
		if ($result = $conn->query($sql)) {

		    // $row_cnt = $result->num_rows;
		    $row_cnt = mysqli_num_rows($result);

		    if ($result = $conn->query($sql)) {
				while($row = $result->fetch_array())
			  	{
				  	$duplicated_id = $row['id'];		  	
				  	break;
				}
			}

		    /* free result set */
		    $result->free();
		}

		if( $duplicated_id > 0 && $duplicated_id < $prev_max_block_id )
		{
			$prev_max_block_id = $duplicated_id;
			// echo $duplicated_id;
		}

		if( $row_cnt < 1 )
		{
			$sql = sprintf("INSERT INTO ticket (symbol, created_at, value, last_digit, total, single_total)	VALUES ('%s', '%s', %s, '%s', '%d', '%d')", 
			$symbol, $times, $value, $last_digit, $total, $single_total);

			mysqli_query($conn, $sql);	
		}
		else
		{
			$sql = sprintf("UPDATE ticket SET value = '%s', last_digit = '%s', total = '%s', single_total = '%s' WHERE created_at = '%s'", 
			$value, $last_digit, $total, $single_total, $times);

			mysqli_query($conn, $sql);	

			echo 'Updated: ' . $times . '<br>';
		}
	}

	echo $prev_max_block_id;

	$sql = sprintf("SELECT id, value FROM ticket WHERE id >= %d", $prev_max_block_id);

	echo $sql;

	$last_digit_array = [];

	if ($result = $conn->query($sql)) {
		while($row = $result->fetch_array())
	  	{
		  	$value = $row['value'];
		  	$id = $row['id'];

		  	$last_digit = substr($value, -1);

		  	$sql = sprintf("SELECT id FROM ticket WHERE last_digit = %d and id < %d order by id desc limit 1", $last_digit, $id);
		  	$sub_result = $conn->query($sql);

		  	$prev_max_id = -1;
		  	while($sub_row = $sub_result->fetch_array())
	  		{
	  			$prev_max_id = $sub_row['id'];
	  			break;
	  		}

	  		$sub_result->free();

	  		if( $prev_max_id < 0 )
	  		{
	  			$gap = '';
	  		}
	  		else
	  		{
	  			$gap = $id - $prev_max_id;
	  		}

	  		$sql = sprintf("UPDATE ticket SET gap = '%s' WHERE id = %d",  $gap, $id);

			mysqli_query($conn, $sql);	
	  	}	
	    
	    /* free result set */
	    $result->free();
	}

	$conn->close();
}