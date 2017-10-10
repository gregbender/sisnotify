<?
	# sis.php
	# handles traversing database and checking each persons course listing,
	# it will notify them if their course is open, and then delete them from the database
	# consists of the following functions:
	# notify, grabcourse, newtoken, getline, parseline, add_deleted_list, delete_old
	# required files: mysqlstuff.php


		# get sql server name and other variables
		include('mysqlstuff.php');

		# this array will hold everyone that needs to be deleted from the main table
		$the_array = array();

		$dbname = 'sis';
		$tablename = 'sis';

		mysql_connect( $host, $user, $password );

		# grab all table elements
		$query = 'SELECT * from '.$tablename;
		$result = mysql_db_query( $dbname, $query );

		# get each row of data
		while ($row = mysql_fetch_array($result)) {

			$personname = $row["name"];
			$theid = $row["id"];
			$email = $row["email"];
			$quarter = $row["quarter"];
			$year = $row["year"];
			$coursenumber = $row["course"];

			# find out of course is open or closed
			$course = grabcourse( $quarter, $year, $coursenumber );

			if ( $course == "Open" ) {

				#echo $row.$email.$quarter.$year.$coursenumber;

				# if course is open, email the person
				notify( $email, $coursenumber );

				# make backup of person into old database
				$query = "INSERT INTO sisold (name, email, quarter, year, course) VALUES
							('$personname', '$email', '$quarter', '$year', '$coursenumber')";

				$result2 = mysql_db_query( $dbname, $query );

				# add this id to the list of things needed to be deleted
				add_deleted_list( $theid );
			}
		}

		# delete everyone from database that has been notified
		delete_old( );


	# notify
	# this function sends an email says the course is open
	# email - persons email address
	# number - the course number that is now open
	# returns - nothing
	function notify( $email, $number ) {

		$subject = "SIS Course Open";
		$text = "Course number: ".$number." is now marked as open.\nhttp://sis.gregbender.com";
		mail( $email, $subject, $text );
	}

	# grabcourse
	# this function actually connects to SIS, and will return a string of either
	# open if the course is open, or closed if it is closed
	# quarter - the quarter of this course
	# year - the year of this course
	# coursenumber - this courses number
	function grabcourse( $quarter, $year, $coursenumber ) {

		# set all parameters that will be needed in the SIS url
		$sisaddress = "http://ritmvs.rit.edu:83/XWEBCONV/CWBA/XSMBWEBM/SR085.STR";
		$param1 = "CONVTOKEN=";
		$param2 = "QUARTER=".$quarter;
		$param3 = "YEAR=".$year;
		$param4 = "DISCIPLINE=";
		$param5 = "INIT=NO";
		$param6 = "PAGE=";

		# get the new token

		# complete each of the parameters for the sis url
		$token = newtoken();
		$param1 = $param1.$token;
		$page = 1;
		$discipline = substr( $coursenumber,0,4 );
		$param4 = $param4.$discipline;

		$coursenum = substr ($coursenumber, 5);
		$entirelist = "";
		$found2 = false;
		$searchstop = "href";
		$status = "Unknown";

		# keep connecting to sis until either the course is found
		# or until we have checked 6 pages
		while ( ($status == "Unknown") && ($page < 6) ) {

			# compile the sis url
			$sislink = $sisaddress."?".$param1."&".$param2."&".$param3."&".
						$param4."&".$param5."&".$param6."0".$page;
						

			#this fails
			$courselist = fopen( $sislink, r );


			# parse through the file, find and grab the course entry we are looking for
			while ((!feof ($courselist)) && (!$flag || !$flag2)) {

				$line = fgets( $courselist, 4096 );
				$found = strpos ($line, $coursenum);
				
				if ( $flag ) {

					$found2 = strpos ($line, "href");

					if ($found2) {
						$flag2=true;
					}
					else {
						$entirelist = $entirelist.$line;
					}
				}
				if ( $found ) {
								$flag = true;
				}


			} # end while

			# close open connection
			fclose( $courselist );

			$listing = strip_tags($entirelist);

			# set status based on string found on webpage
			if ( strpos($listing, "Open") ) {
				$status = "Open";
			}
			else if ( strpos($listing, "Close") ) {
				$status = "Closed";
			}
			else {
				$status = "Unknown";
			}
			$page++;

			# used to delay connections, as to not overwealm server
			$waitcounter = 0;
			sleep(3);			
			#while ($waitcounter < 6000) {
			#	echo $waitcounter."\n";
			#	$waitcounter++;
			#}

		} # end while
		
		#delete old token
		del_token( $token );
		
		echo $coursenumber.": ".$status."\n";
		return $status;
	} # end grabcourse


	# newtoken
	# the function connects to SIS and get's a new token
	# returns - a string of the new token
	function newtoken() {

		$sislink = "http://ritmvs.rit.edu:83/XWEBCONV/CWBA/XSMBWEBM/SR085.STR?INIT=YES&CONVTOKEN=INIT";
		$query = "<input type=\"hidden\" name=\"CONVTOKEN";

		$line = getline( $sislink, $query );
		$token = parseline( $line );

		return $token;

	} # end newtoken

	# getline
	# Searches through entire page, and returns the first line found with the word CONVTOKEN
	# sislink - the url to connect to
	# query_string - the string we are searching for, in this case CONVTOKEN
	function getline( $sislink, $query_str ) {

		$sisfile = fopen( $sislink, r );

		while (!feof ($sisfile) && !$found) {

			$line = fgets($sisfile, 4096);
			
			$found = strpos ($line, $query_str);
		}
		return $line;

	} # end getline

	# parseline
	# Parses the line with CONVTOKEN to find the value associated with that variable
	# line - the string containing convtoken
	function parseline( $line ) {

		#constant used to determine where value starts
		$begconstant = 7;
		#constant used at end of number
		$endconstant = "\">";

		$search = "value";

		$valposition = strpos( $line, $search );
		$valposition = $valposition + $begconstant;
		$valendposition = strpos( $line, $endconstant );
		$valendposition += 1; #correction to get after number

		$number = substr ( $line, $valposition, $valendposition );
		$number = substr( trim($number), 0, -2);

		return $number;
	} # end parseline

	# add_deleted_list
	# this function will add the id number to an array of people that need to be deleted
	# the_id - the id of the person needed to be deleted

	function add_deleted_list( $the_id ) {
		global $the_array;
		array_push ( $the_array, $the_id );
	}
	
	#this sends a command to delete the current token
	function del_token( $token ) {
	
		if ( $token != "") {
			$query = "http://ritmvs.rit.edu:83/XWEBCONV/CWBA/XSMBWEBM/XSTDEND.STR?CONVTOKEN=".$token."&NAVTO=EXIT";
			$sisfile = fopen( $query, r );
			fclose( $sisfile );
		}
	}

	# delete_old
	# this function deletes all the entries that have already been emailed
	function delete_old() {

		global $the_array;
		global	$dbname;
		$item = array_pop( $the_array );

		while ( $item ) {

			# delete person from current database
			$query = "DELETE FROM sis WHERE id = '$item'";
			$result3 = mysql_db_query( $dbname, $query );
			$item = array_pop( $the_array );
		}
	} # end delete_old
?>