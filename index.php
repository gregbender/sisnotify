<?
	# index.php
	# required files: mysqlstuff.php, success.htm, form.htm
	# this page will display a form for the user to fill out and submit
	# it will then take that form and insert it into a database

	# see if the form has been filled out
	if ($course) {

		# get mysql hostname and password
		include('mysqlstuff.php');

		# database and table names
		$dbname = 'sis';
		$tablename = 'sis';

		mysql_connect( $host, $user, $password );

		# put form into the database
		$query = "INSERT INTO $tablename (name, email, quarter, year, course) VALUES
					('$name', '$email', '$quarter', '$year', '$course')";

		$result = mysql_db_query( $dbname, $query );

		# if form has been submitted, show approperiate page
		# otherwise show error message
		if ($result) {
			include('success.htm');
		}
		else {
			echo "Submission Failed - Please notify pure7power@hotmail.com";
		}
	} # end if

# if form hasn't been filled out, display the form
else {
		# the submission form
		include('form.htm');
	}
?>