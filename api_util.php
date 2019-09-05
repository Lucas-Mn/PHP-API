<?php

	function error(string $message){
				http_response_code(500);
				echo $message;
				exit; }

?>