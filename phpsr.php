<?php
	// settings
	mb_internal_encoding("utf-8");
	date_default_timezone_set("Europe/Stockholm");
	ini_set("memory_limit","256M");
	set_time_limit(0);
	set_error_handler("internalerror");
	$options="d:o:r:s:e:m:c:u:i:h::";

	// functions
	function consolewrite($input) {
		print("[".date("Y-m-d H:i:s")."] ".$input."\n");
	}

	function internalerror($errno,$errstr) {
		consolewrite("Error: [".$errno."] ".$errstr);
	}

	function checkodbc($odbc) {
		if(@odbc_connect($odbc,"","",SQL_CUR_USE_ODBC)==FALSE) {
		    echo "Invalid odbc connection!\n";
		    exit;
		}
	}

	function checkstartend($options) {
		$opts=getopt($options);
		if(isset($opts["m"]) && $opts["m"]<>"") {
			return(subtractmonth($opts["m"]));
		} elseif(!isset($opts["s"]) || $opts["s"]=="") {
			consolewrite("Missing startdate, aborting!");
			exit;
		} elseif(!isset($opts["e"]) || $opts["e"]=="") {
			consolewrite("Missing enddate, aborting!");
			exit;
		} else {
			return($opts["s"].";".$opts["e"]);
		}
	}

	function subtractmonth($input) {
		$input=str_replace("-","",$input);
		$thedate=strtotime(date("Y-m-d")." -".$input." months");
		$firstday=date("Y-m-",$thedate)."01";
		$lastday=date("Y-m-",$thedate).date("d",mktime(0,0,0,date("m",$thedate)+1,0,date("Y",$thedate)));
	return($firstday.";".$lastday);
	}

	function generateoutput($report,$data,$outfile,$startdate,$enddate) {
		consolewrite("Saving report file ...");
		switch($report) {
			case "ccprinting":
				$reportname="Cost Code Printing";
				break;
			case "ccprintingdetailed":
				$reportname="Cost Code Printing (detailed)";
				break;
			case "ccprintingless":
				$reportname="Cost Code Printing (less)";
				break;
			case "ccuserprint":
				$reportname="Cost Code User Printing";
				break;
			case "userprint":
				$reportname="User Printing";
				break;
			case "deviceprint":
				$reportname="Device Printing";
				break;
			case "devicetracking":
				$reportname="Device Tracking";
				break;
			default:
				$reportname=$report;
		}
		if($outfile<>"") {
			$filename=str_replace(".csv","",$outfile)."-".$startdate."_to_".$enddate.".csv";
		} else {
			$filename=$report."-".$startdate."_to_".$enddate.".csv";
		}
		try {
			$outfile=fopen($filename,"w");
			fwrite($outfile,$data);
			fclose($outfile);
		} catch(ErrorException $e) {
			if(isset($e) && !empty($e)){
				consolewrite($e);
				exit;
			}	
		}
	}

	function printhelp() {
		echo "Usage: php phpsc.php [OPTION] ...\n\n";
		echo "  -h    print this help\n";
		echo "  -d    odbc connection name\n";
		echo "  -o    output filename\n";
		echo "  -r    report to use (see reports.txt)\n";
		echo "  -s    startdate (yyyy-mm-dd)\n";
		echo "  -e    enddate (yyyy-mm-dd)\n";
		echo "  -m    subtract months from current date\n";
		echo "  -c    cost code\n";
		echo "  -u    username\n";
		echo "  -i    device id\n\n";
	}

	// report functions
	function rep_ccprinting($odbc,$startdate,$enddate,$outfile) {
		consolewrite("Generating cost code printing report ...");
			$conn=odbc_connect($odbc,"","");
				$sql=odbc_prepare($conn,"SELECT TrackingPageCount,JobType,JobPageFormat,Price,TrackingColorPageCount,UserCostCode,JobSheetCount FROM sctracking.dbo.scTracking WHERE (JobType='1' OR JobType='2' OR JobType='3') AND (StartDateTime BETWEEN '".$startdate." 00:00:00' AND '".$enddate." 23:59:59')");
				consolewrite("Collecting data from safecom ...");
					odbc_execute($sql);
						consolewrite("Compiles data ...");
							$costcodedata=array();
							while($data=odbc_fetch_array($sql)) {
								if(!array_key_exists($data["UserCostCode"],$costcodedata)) {
									$costcodedata[$data["UserCostCode"]]["print_a4_bw"]=0;
									$costcodedata[$data["UserCostCode"]]["print_a4_clr"]=0;
									$costcodedata[$data["UserCostCode"]]["print_a3_bw"]=0;
									$costcodedata[$data["UserCostCode"]]["print_a3_clr"]=0;
									$costcodedata[$data["UserCostCode"]]["print_other_bw"]=0;
									$costcodedata[$data["UserCostCode"]]["print_other_clr"]=0;
									$costcodedata[$data["UserCostCode"]]["copy_a4_bw"]=0;
									$costcodedata[$data["UserCostCode"]]["copy_a4_clr"]=0;
									$costcodedata[$data["UserCostCode"]]["copy_a3_bw"]=0;
									$costcodedata[$data["UserCostCode"]]["copy_a3_clr"]=0;
									$costcodedata[$data["UserCostCode"]]["copy_other_bw"]=0;
									$costcodedata[$data["UserCostCode"]]["copy_other_clr"]=0;
									$costcodedata[$data["UserCostCode"]]["a4_sheets"]=0;
									$costcodedata[$data["UserCostCode"]]["a3_sheets"]=0;
									$costcodedata[$data["UserCostCode"]]["other_sheets"]=0;
									$costcodedata[$data["UserCostCode"]]["totalcost"]=0;
								}
								if($data["JobType"]=="1" OR $data["JobType"]=="2") {
									if($data["JobPageFormat"]=="A4") {
										$costcodedata[$data["UserCostCode"]]["print_a4_bw"]=$costcodedata[$data["UserCostCode"]]["print_a4_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["print_a4_clr"]=$costcodedata[$data["UserCostCode"]]["print_a4_clr"]+$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["a4_sheets"]=$costcodedata[$data["UserCostCode"]]["a4_sheets"]+$data["JobSheetCount"];
										$costcodedata[$data["UserCostCode"]]["totalcost"]=$costcodedata[$data["UserCostCode"]]["totalcost"]+$data["Price"];
									} elseif($data["JobPageFormat"]=="A3") {
										$costcodedata[$data["UserCostCode"]]["print_a3_bw"]=$costcodedata[$data["UserCostCode"]]["print_a3_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["print_a3_clr"]=$costcodedata[$data["UserCostCode"]]["print_a3_clr"]+$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["a3_sheets"]=$costcodedata[$data["UserCostCode"]]["a3_sheets"]+$data["JobSheetCount"];
										$costcodedata[$data["UserCostCode"]]["totalcost"]=$costcodedata[$data["UserCostCode"]]["totalcost"]+$data["Price"];
									} else {
										$costcodedata[$data["UserCostCode"]]["print_other_bw"]=$costcodedata[$data["UserCostCode"]]["print_other_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["print_other_clr"]=$costcodedata[$data["UserCostCode"]]["print_other_clr"]+$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["other_sheets"]=$costcodedata[$data["UserCostCode"]]["other_sheets"]+$data["JobSheetCount"];
										$costcodedata[$data["UserCostCode"]]["totalcost"]=$costcodedata[$data["UserCostCode"]]["totalcost"]+$data["Price"];
									}
								}
								if($data["JobType"]=="3") {
									if($data["JobPageFormat"]=="A4") {
										$costcodedata[$data["UserCostCode"]]["copy_a4_bw"]=$costcodedata[$data["UserCostCode"]]["copy_a4_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["copy_a4_clr"]=$costcodedata[$data["UserCostCode"]]["copy_a4_clr"]+$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["a4_sheets"]=$costcodedata[$data["UserCostCode"]]["a4_sheets"]+$data["JobSheetCount"];
										$costcodedata[$data["UserCostCode"]]["totalcost"]=$costcodedata[$data["UserCostCode"]]["totalcost"]+$data["Price"];
									} elseif($data["JobPageFormat"]=="A3") {
										$costcodedata[$data["UserCostCode"]]["copy_a3_bw"]=$costcodedata[$data["UserCostCode"]]["copy_a3_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["copy_a3_clr"]=$costcodedata[$data["UserCostCode"]]["copy_a3_clr"]+$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["a3_sheets"]=$costcodedata[$data["UserCostCode"]]["a3_sheets"]+$data["JobSheetCount"];
										$costcodedata[$data["UserCostCode"]]["totalcost"]=$costcodedata[$data["UserCostCode"]]["totalcost"]+$data["Price"];
									} else {
										$costcodedata[$data["UserCostCode"]]["copy_other_bw"]=$costcodedata[$data["UserCostCode"]]["copy_other_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["copy_other_clr"]=$costcodedata[$data["UserCostCode"]]["copy_other_clr"]+$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["other_sheets"]=$costcodedata[$data["UserCostCode"]]["other_sheets"]+$data["JobSheetCount"];
										$costcodedata[$data["UserCostCode"]]["totalcost"]=$costcodedata[$data["UserCostCode"]]["totalcost"]+$data["Price"];
									}
								}
							}
			odbc_close($conn);
		consolewrite("Generating output ...");
			$outputdata="Costcode;Print A4 BW;Print A4 Color;Print A3 BW;Print A3 Color;Print Other BW;Print Other Color;Copy A4 BW;Copy A4 Color;Copy A3 BW;Copy A3 Color;Copy Other BW;Copy Other Color;A4 Sheets;A3 Sheets;Other Sheets;Cost\r\n";
			foreach($costcodedata as $key => $value) {
				if($key=="") {
					$outputdata.="N/A;";
				} else {
					$outputdata.=$key.";";
				}
				$outputdata.=$costcodedata[$key]["print_a4_bw"].";";
				$outputdata.=$costcodedata[$key]["print_a4_clr"].";";
				$outputdata.=$costcodedata[$key]["print_a3_bw"].";";
				$outputdata.=$costcodedata[$key]["print_a3_clr"].";";
				$outputdata.=$costcodedata[$key]["print_other_bw"].";";
				$outputdata.=$costcodedata[$key]["print_other_clr"].";";
				$outputdata.=$costcodedata[$key]["copy_a4_bw"].";";
				$outputdata.=$costcodedata[$key]["copy_a4_clr"].";";
				$outputdata.=$costcodedata[$key]["copy_a3_bw"].";";
				$outputdata.=$costcodedata[$key]["copy_a3_clr"].";";
				$outputdata.=$costcodedata[$key]["copy_other_bw"].";";
				$outputdata.=$costcodedata[$key]["copy_other_clr"].";";
				$outputdata.=$costcodedata[$key]["a4_sheets"].";";
				$outputdata.=$costcodedata[$key]["a3_sheets"].";";
				$outputdata.=$costcodedata[$key]["other_sheets"].";";
				$outputdata.=number_format(trim($costcodedata[$key]["totalcost"]),2,",","");
				$outputdata.="\r\n";
			}
		generateoutput("ccprinting",$outputdata,$outfile,$startdate,$enddate);
		consolewrite("Done!");
	}

	function rep_ccprintingdetailed($odbc,$startdate,$enddate,$outfile) {
		consolewrite("Generating cost code printing (detailed) report ...");
			$conn=odbc_connect($odbc,"","");
				$sql=odbc_prepare($conn,"SELECT TrackingPageCount,JobType,JobPageFormat,Price,TrackingColorPageCount,UserCostCode,JobSheetCount FROM sctracking.dbo.scTracking WHERE (JobType='1' OR JobType='2' OR JobType='3') AND (StartDateTime BETWEEN '".$startdate." 00:00:00' AND '".$enddate." 23:59:59')");
				consolewrite("Collecting data from safecom ...");
					odbc_execute($sql);
						consolewrite("Compiles data ...");
							$costcodedata=array();
							while($data=odbc_fetch_array($sql)) {
								if(!array_key_exists($data["UserCostCode"],$costcodedata)) {
									$costcodedata[$data["UserCostCode"]]["push_a4_bw"]=0;
									$costcodedata[$data["UserCostCode"]]["push_a4_clr"]=0;
									$costcodedata[$data["UserCostCode"]]["push_a3_bw"]=0;
									$costcodedata[$data["UserCostCode"]]["push_a3_clr"]=0;
									$costcodedata[$data["UserCostCode"]]["push_other_bw"]=0;
									$costcodedata[$data["UserCostCode"]]["push_other_clr"]=0;
									$costcodedata[$data["UserCostCode"]]["pull_a4_bw"]=0;
									$costcodedata[$data["UserCostCode"]]["pull_a4_clr"]=0;
									$costcodedata[$data["UserCostCode"]]["pull_a3_bw"]=0;
									$costcodedata[$data["UserCostCode"]]["pull_a3_clr"]=0;
									$costcodedata[$data["UserCostCode"]]["pull_other_bw"]=0;
									$costcodedata[$data["UserCostCode"]]["pull_other_clr"]=0;
									$costcodedata[$data["UserCostCode"]]["copy_a4_bw"]=0;
									$costcodedata[$data["UserCostCode"]]["copy_a4_clr"]=0;
									$costcodedata[$data["UserCostCode"]]["copy_a3_bw"]=0;
									$costcodedata[$data["UserCostCode"]]["copy_a3_clr"]=0;
									$costcodedata[$data["UserCostCode"]]["copy_other_bw"]=0;
									$costcodedata[$data["UserCostCode"]]["copy_other_clr"]=0;
									$costcodedata[$data["UserCostCode"]]["a4_sheets"]=0;
									$costcodedata[$data["UserCostCode"]]["a3_sheets"]=0;
									$costcodedata[$data["UserCostCode"]]["other_sheets"]=0;
									$costcodedata[$data["UserCostCode"]]["totalcost"]=0;
								}
								if($data["JobType"]=="1") {
									if($data["JobPageFormat"]=="A4") {
										$costcodedata[$data["UserCostCode"]]["push_a4_bw"]=$costcodedata[$data["UserCostCode"]]["push_a4_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["push_a4_clr"]=$costcodedata[$data["UserCostCode"]]["push_a4_clr"]+$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["a4_sheets"]=$costcodedata[$data["UserCostCode"]]["a4_sheets"]+$data["JobSheetCount"];
										$costcodedata[$data["UserCostCode"]]["totalcost"]=$costcodedata[$data["UserCostCode"]]["totalcost"]+$data["Price"];
									} elseif($data["JobPageFormat"]=="A3") {
										$costcodedata[$data["UserCostCode"]]["push_a3_bw"]=$costcodedata[$data["UserCostCode"]]["push_a3_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["push_a3_clr"]=$costcodedata[$data["UserCostCode"]]["push_a3_clr"]+$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["a3_sheets"]=$costcodedata[$data["UserCostCode"]]["a3_sheets"]+$data["JobSheetCount"];
										$costcodedata[$data["UserCostCode"]]["totalcost"]=$costcodedata[$data["UserCostCode"]]["totalcost"]+$data["Price"];
									} else {
										$costcodedata[$data["UserCostCode"]]["push_other_bw"]=$costcodedata[$data["UserCostCode"]]["push_other_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["push_other_clr"]=$costcodedata[$data["UserCostCode"]]["push_other_clr"]+$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["other_sheets"]=$costcodedata[$data["UserCostCode"]]["other_sheets"]+$data["JobSheetCount"];
										$costcodedata[$data["UserCostCode"]]["totalcost"]=$costcodedata[$data["UserCostCode"]]["totalcost"]+$data["Price"];
									}
								}
								if($data["JobType"]=="2") {
									if($data["JobPageFormat"]=="A4") {
										$costcodedata[$data["UserCostCode"]]["pull_a4_bw"]=$costcodedata[$data["UserCostCode"]]["pull_a4_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["pull_a4_clr"]=$costcodedata[$data["UserCostCode"]]["pull_a4_clr"]+$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["a4_sheets"]=$costcodedata[$data["UserCostCode"]]["a4_sheets"]+$data["JobSheetCount"];
										$costcodedata[$data["UserCostCode"]]["totalcost"]=$costcodedata[$data["UserCostCode"]]["totalcost"]+$data["Price"];
									} elseif($data["JobPageFormat"]=="A3") {
										$costcodedata[$data["UserCostCode"]]["pull_a3_bw"]=$costcodedata[$data["UserCostCode"]]["pull_a3_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["pull_a3_clr"]=$costcodedata[$data["UserCostCode"]]["pull_a3_clr"]+$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["a3_sheets"]=$costcodedata[$data["UserCostCode"]]["a3_sheets"]+$data["JobSheetCount"];
										$costcodedata[$data["UserCostCode"]]["totalcost"]=$costcodedata[$data["UserCostCode"]]["totalcost"]+$data["Price"];
									} else {
										$costcodedata[$data["UserCostCode"]]["pull_other_bw"]=$costcodedata[$data["UserCostCode"]]["pull_other_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["pull_other_clr"]=$costcodedata[$data["UserCostCode"]]["pull_other_clr"]+$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["other_sheets"]=$costcodedata[$data["UserCostCode"]]["other_sheets"]+$data["JobSheetCount"];
										$costcodedata[$data["UserCostCode"]]["totalcost"]=$costcodedata[$data["UserCostCode"]]["totalcost"]+$data["Price"];
									}
								}
								if($data["JobType"]=="3") {
									if($data["JobPageFormat"]=="A4") {
										$costcodedata[$data["UserCostCode"]]["copy_a4_bw"]=$costcodedata[$data["UserCostCode"]]["copy_a4_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["copy_a4_clr"]=$costcodedata[$data["UserCostCode"]]["copy_a4_clr"]+$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["a4_sheets"]=$costcodedata[$data["UserCostCode"]]["a4_sheets"]+$data["JobSheetCount"];
										$costcodedata[$data["UserCostCode"]]["totalcost"]=$costcodedata[$data["UserCostCode"]]["totalcost"]+$data["Price"];
									} elseif($data["JobPageFormat"]=="A3") {
										$costcodedata[$data["UserCostCode"]]["copy_a3_bw"]=$costcodedata[$data["UserCostCode"]]["copy_a3_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["copy_a3_clr"]=$costcodedata[$data["UserCostCode"]]["copy_a3_clr"]+$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["a3_sheets"]=$costcodedata[$data["UserCostCode"]]["a3_sheets"]+$data["JobSheetCount"];
										$costcodedata[$data["UserCostCode"]]["totalcost"]=$costcodedata[$data["UserCostCode"]]["totalcost"]+$data["Price"];
									} else {
										$costcodedata[$data["UserCostCode"]]["copy_other_bw"]=$costcodedata[$data["UserCostCode"]]["copy_other_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["copy_other_clr"]=$costcodedata[$data["UserCostCode"]]["copy_other_clr"]+$data["TrackingColorPageCount"];
										$costcodedata[$data["UserCostCode"]]["other_sheets"]=$costcodedata[$data["UserCostCode"]]["other_sheets"]+$data["JobSheetCount"];
										$costcodedata[$data["UserCostCode"]]["totalcost"]=$costcodedata[$data["UserCostCode"]]["totalcost"]+$data["Price"];
									}
								}
							}
			odbc_close($conn);
		consolewrite("Generating output ...");
			$outputdata="Costcode;Pushprint A4 BW;Pushprint A4 Color;Pushprint A3 BW;Pushprint A3 Color;Pushprint Other BW;Pushprint Other Color;Pullprint A4 BW;Pullprint A4 Color;Pullprint A3 BW;Pullprint A3 Color;Pullprint Other BW;Pullprint Other Color;Copy A4 BW;Copy A4 Color;Copy A3 BW;Copy A3 Color;Copy Other BW;Copy Other Color;A4 Sheets;A3 Sheets;Other Sheets;Cost\r\n";
			foreach($costcodedata as $key => $value) {
				if($key=="") {
					$outputdata.="N/A;";
				} else {
					$outputdata.=$key.";";
				}
				$outputdata.=$costcodedata[$key]["push_a4_bw"].";";
				$outputdata.=$costcodedata[$key]["push_a4_clr"].";";
				$outputdata.=$costcodedata[$key]["push_a3_bw"].";";
				$outputdata.=$costcodedata[$key]["push_a3_clr"].";";
				$outputdata.=$costcodedata[$key]["push_other_bw"].";";
				$outputdata.=$costcodedata[$key]["push_other_clr"].";";
				$outputdata.=$costcodedata[$key]["pull_a4_bw"].";";
				$outputdata.=$costcodedata[$key]["pull_a4_clr"].";";
				$outputdata.=$costcodedata[$key]["pull_a3_bw"].";";
				$outputdata.=$costcodedata[$key]["pull_a3_clr"].";";
				$outputdata.=$costcodedata[$key]["pull_other_bw"].";";
				$outputdata.=$costcodedata[$key]["pull_other_clr"].";";
				$outputdata.=$costcodedata[$key]["copy_a4_bw"].";";
				$outputdata.=$costcodedata[$key]["copy_a4_clr"].";";
				$outputdata.=$costcodedata[$key]["copy_a3_bw"].";";
				$outputdata.=$costcodedata[$key]["copy_a3_clr"].";";
				$outputdata.=$costcodedata[$key]["copy_other_bw"].";";
				$outputdata.=$costcodedata[$key]["copy_other_clr"].";";
				$outputdata.=$costcodedata[$key]["a4_sheets"].";";
				$outputdata.=$costcodedata[$key]["a3_sheets"].";";
				$outputdata.=$costcodedata[$key]["other_sheets"].";";
				$outputdata.=number_format(trim($costcodedata[$key]["totalcost"]),2,",","");
				$outputdata.="\r\n";
			}
		generateoutput("ccprintingdetailed",$outputdata,$outfile,$startdate,$enddate);
		consolewrite("Done!");
	}

	function rep_ccprintingless($odbc,$startdate,$enddate,$outfile) {
		consolewrite("Generating cost code printing (less) report ...");
			$conn=odbc_connect($odbc,"","");
				$sql=odbc_prepare($conn,"SELECT TrackingPageCount,JobType,JobPageFormat,Price,TrackingColorPageCount,UserCostCode,JobSheetCount FROM sctracking.dbo.scTracking WHERE (JobType='1' OR JobType='2' OR JobType='3') AND (StartDateTime BETWEEN '".$startdate." 00:00:00' AND '".$enddate." 23:59:59')");
				consolewrite("Collecting data from safecom ...");
					odbc_execute($sql);
						consolewrite("Compiles data ...");
							$costcodedata=array();
							while($data=odbc_fetch_array($sql)) {
								if(!array_key_exists($data["UserCostCode"],$costcodedata)) {
									$costcodedata[$data["UserCostCode"]]["a4_bw"]=0;
									$costcodedata[$data["UserCostCode"]]["a4_clr"]=0;
									$costcodedata[$data["UserCostCode"]]["a3_bw"]=0;
									$costcodedata[$data["UserCostCode"]]["a3_clr"]=0;
									$costcodedata[$data["UserCostCode"]]["other_bw"]=0;
									$costcodedata[$data["UserCostCode"]]["other_clr"]=0;
									$costcodedata[$data["UserCostCode"]]["a4_sheets"]=0;
									$costcodedata[$data["UserCostCode"]]["a3_sheets"]=0;
									$costcodedata[$data["UserCostCode"]]["other_sheets"]=0;
									$costcodedata[$data["UserCostCode"]]["totalcost"]=0;
								}
								if($data["JobPageFormat"]=="A4") {
									$costcodedata[$data["UserCostCode"]]["a4_bw"]=$costcodedata[$data["UserCostCode"]]["a4_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
									$costcodedata[$data["UserCostCode"]]["a4_clr"]=$costcodedata[$data["UserCostCode"]]["a4_clr"]+$data["TrackingColorPageCount"];
									$costcodedata[$data["UserCostCode"]]["a4_sheets"]=$costcodedata[$data["UserCostCode"]]["a4_sheets"]+$data["JobSheetCount"];
									$costcodedata[$data["UserCostCode"]]["totalcost"]=$costcodedata[$data["UserCostCode"]]["totalcost"]+$data["Price"];
								} elseif($data["JobPageFormat"]=="A3") {
									$costcodedata[$data["UserCostCode"]]["a3_bw"]=$costcodedata[$data["UserCostCode"]]["a3_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
									$costcodedata[$data["UserCostCode"]]["a3_clr"]=$costcodedata[$data["UserCostCode"]]["a3_clr"]+$data["TrackingColorPageCount"];
									$costcodedata[$data["UserCostCode"]]["a3_sheets"]=$costcodedata[$data["UserCostCode"]]["a3_sheets"]+$data["JobSheetCount"];
									$costcodedata[$data["UserCostCode"]]["totalcost"]=$costcodedata[$data["UserCostCode"]]["totalcost"]+$data["Price"];
								} else {
									$costcodedata[$data["UserCostCode"]]["other_bw"]=$costcodedata[$data["UserCostCode"]]["other_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
									$costcodedata[$data["UserCostCode"]]["other_clr"]=$costcodedata[$data["UserCostCode"]]["other_clr"]+$data["TrackingColorPageCount"];
									$costcodedata[$data["UserCostCode"]]["other_sheets"]=$costcodedata[$data["UserCostCode"]]["other_sheets"]+$data["JobSheetCount"];
									$costcodedata[$data["UserCostCode"]]["totalcost"]=$costcodedata[$data["UserCostCode"]]["totalcost"]+$data["Price"];
								}
							}
			odbc_close($conn);
		consolewrite("Generating output ...");
			$outputdata="Costcode;A4 BW;A4 Color;A3 BW;A3 Color;Other BW;Other Color;A4 Sheets;A3 Sheets;Other Sheets;Cost\r\n";
			foreach($costcodedata as $key => $value) {
				if($key=="") {
					$outputdata.="N/A;";
				} else {
					$outputdata.=$key.";";
				}
				$outputdata.=$costcodedata[$key]["a4_bw"].";";
				$outputdata.=$costcodedata[$key]["a4_clr"].";";
				$outputdata.=$costcodedata[$key]["a3_bw"].";";
				$outputdata.=$costcodedata[$key]["a3_clr"].";";
				$outputdata.=$costcodedata[$key]["other_bw"].";";
				$outputdata.=$costcodedata[$key]["other_clr"].";";
				$outputdata.=$costcodedata[$key]["a4_sheets"].";";
				$outputdata.=$costcodedata[$key]["a3_sheets"].";";
				$outputdata.=$costcodedata[$key]["other_sheets"].";";
				$outputdata.=number_format(trim($costcodedata[$key]["totalcost"]),2,",","");
				$outputdata.="\r\n";
			}
		generateoutput("ccprintingless",$outputdata,$outfile,$startdate,$enddate);
		consolewrite("Done!");
	}

	function rep_ccuserprint($odbc,$costcode,$startdate,$enddate,$outfile) {
		consolewrite("Generating cost code user printing report ...");
			$conn=odbc_connect($odbc,"","");
				$sql=odbc_prepare($conn,"SELECT UserFullName,JobSubmitLogon,TrackingPageCount,JobType,JobPageFormat,Price,TrackingColorPageCount,JobSheetCount FROM sctracking.dbo.scTracking WHERE (UserCostCode='".$costcode."') AND (JobType='1' OR JobType='2' OR JobType='3') AND (StartDateTime BETWEEN '".$startdate." 00:00:00' AND '".$enddate." 23:59:59')");
				consolewrite("Collecting data from safecom ...");
					odbc_execute($sql);
						consolewrite("Compiles data ...");
							$costcodedata=array();
							while($data=odbc_fetch_array($sql)) {
								if(!array_key_exists($data["JobSubmitLogon"],$costcodedata)) {
									$costcodedata[$data["JobSubmitLogon"]]["userfullname"]=$data["UserFullName"];
									$costcodedata[$data["JobSubmitLogon"]]["a4_bw"]=0;
									$costcodedata[$data["JobSubmitLogon"]]["a4_clr"]=0;
									$costcodedata[$data["JobSubmitLogon"]]["a3_bw"]=0;
									$costcodedata[$data["JobSubmitLogon"]]["a3_clr"]=0;
									$costcodedata[$data["JobSubmitLogon"]]["other_bw"]=0;
									$costcodedata[$data["JobSubmitLogon"]]["other_clr"]=0;
									$costcodedata[$data["JobSubmitLogon"]]["a4_sheets"]=0;
									$costcodedata[$data["JobSubmitLogon"]]["a3_sheets"]=0;
									$costcodedata[$data["JobSubmitLogon"]]["other_sheets"]=0;
									$costcodedata[$data["JobSubmitLogon"]]["totalcost"]=0;
								}
								if($data["JobPageFormat"]=="A4") {
									$costcodedata[$data["JobSubmitLogon"]]["a4_bw"]=$costcodedata[$data["JobSubmitLogon"]]["a4_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
									$costcodedata[$data["JobSubmitLogon"]]["a4_clr"]=$costcodedata[$data["JobSubmitLogon"]]["a4_clr"]+$data["TrackingColorPageCount"];
									$costcodedata[$data["JobSubmitLogon"]]["a4_sheets"]=$costcodedata[$data["JobSubmitLogon"]]["a4_sheets"]+$data["JobSheetCount"];
									$costcodedata[$data["JobSubmitLogon"]]["totalcost"]=$costcodedata[$data["JobSubmitLogon"]]["totalcost"]+$data["Price"];
								} elseif($data["JobPageFormat"]=="A3") {
									$costcodedata[$data["JobSubmitLogon"]]["a3_bw"]=$costcodedata[$data["JobSubmitLogon"]]["a3_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
									$costcodedata[$data["JobSubmitLogon"]]["a3_clr"]=$costcodedata[$data["JobSubmitLogon"]]["a3_clr"]+$data["TrackingColorPageCount"];
									$costcodedata[$data["JobSubmitLogon"]]["a3_sheets"]=$costcodedata[$data["JobSubmitLogon"]]["a3_sheets"]+$data["JobSheetCount"];
									$costcodedata[$data["JobSubmitLogon"]]["totalcost"]=$costcodedata[$data["JobSubmitLogon"]]["totalcost"]+$data["Price"];
								} else {
									$costcodedata[$data["JobSubmitLogon"]]["other_bw"]=$costcodedata[$data["JobSubmitLogon"]]["other_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
									$costcodedata[$data["JobSubmitLogon"]]["other_clr"]=$costcodedata[$data["JobSubmitLogon"]]["other_clr"]+$data["TrackingColorPageCount"];
									$costcodedata[$data["JobSubmitLogon"]]["other_sheets"]=$costcodedata[$data["JobSubmitLogon"]]["other_sheets"]+$data["JobSheetCount"];
									$costcodedata[$data["JobSubmitLogon"]]["totalcost"]=$costcodedata[$data["JobSubmitLogon"]]["totalcost"]+$data["Price"];
								}
							}
			odbc_close($conn);
		consolewrite("Generating output ...");
			$outputdata="Username;Name;A4 BW;A4 Color;A3 BW;A3 Color;Other BW;Other Color;A4 Sheets;A3 Sheets;Other Sheets;Cost\r\n";
			foreach($costcodedata as $key => $value) {
				$outputdata.=$key.";";
				$outputdata.=$costcodedata[$key]["userfullname"].";";
				$outputdata.=$costcodedata[$key]["a4_bw"].";";
				$outputdata.=$costcodedata[$key]["a4_clr"].";";
				$outputdata.=$costcodedata[$key]["a3_bw"].";";
				$outputdata.=$costcodedata[$key]["a3_clr"].";";
				$outputdata.=$costcodedata[$key]["other_bw"].";";
				$outputdata.=$costcodedata[$key]["other_clr"].";";
				$outputdata.=$costcodedata[$key]["a4_sheets"].";";
				$outputdata.=$costcodedata[$key]["a3_sheets"].";";
				$outputdata.=$costcodedata[$key]["other_sheets"].";";
				$outputdata.=number_format(trim($costcodedata[$key]["totalcost"]),2,",","");
				$outputdata.="\r\n";
			}
		generateoutput("ccuserprint",$outputdata,$outfile,$startdate,$enddate);
		consolewrite("Done!");
	}

	function rep_userprint($odbc,$username,$startdate,$enddate,$outfile) {
		consolewrite("Generating user printing report ...");
			$conn=odbc_connect($odbc,"","");
				$sql=odbc_prepare($conn,"SELECT UserFullName,JobSubmitLogon,TrackingPageCount,JobType,JobPageFormat,Price,TrackingColorPageCount,JobSheetCount FROM sctracking.dbo.scTracking WHERE (JobSubmitLogon='".$username."') AND (JobType='1' OR JobType='2' OR JobType='3') AND (StartDateTime BETWEEN '".$startdate." 00:00:00' AND '".$enddate." 23:59:59')");
				consolewrite("Collecting data from safecom ...");
					odbc_execute($sql);
						consolewrite("Compiles data ...");
							$userdata=array();
							while($data=odbc_fetch_array($sql)) {
								if(!array_key_exists($data["JobSubmitLogon"],$userdata)) {
									$userdata[$data["JobSubmitLogon"]]["userfullname"]=$data["UserFullName"];
									$userdata[$data["JobSubmitLogon"]]["a4_bw"]=0;
									$userdata[$data["JobSubmitLogon"]]["a4_clr"]=0;
									$userdata[$data["JobSubmitLogon"]]["a3_bw"]=0;
									$userdata[$data["JobSubmitLogon"]]["a3_clr"]=0;
									$userdata[$data["JobSubmitLogon"]]["other_bw"]=0;
									$userdata[$data["JobSubmitLogon"]]["other_clr"]=0;
									$userdata[$data["JobSubmitLogon"]]["a4_sheets"]=0;
									$userdata[$data["JobSubmitLogon"]]["a3_sheets"]=0;
									$userdata[$data["JobSubmitLogon"]]["other_sheets"]=0;
									$userdata[$data["JobSubmitLogon"]]["totalcost"]=0;
								}
								if($data["JobPageFormat"]=="A4") {
									$userdata[$data["JobSubmitLogon"]]["a4_bw"]=$userdata[$data["JobSubmitLogon"]]["a4_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
									$userdata[$data["JobSubmitLogon"]]["a4_clr"]=$userdata[$data["JobSubmitLogon"]]["a4_clr"]+$data["TrackingColorPageCount"];
									$userdata[$data["JobSubmitLogon"]]["a4_sheets"]=$userdata[$data["JobSubmitLogon"]]["a4_sheets"]+$data["JobSheetCount"];
									$userdata[$data["JobSubmitLogon"]]["totalcost"]=$userdata[$data["JobSubmitLogon"]]["totalcost"]+$data["Price"];
								} elseif($data["JobPageFormat"]=="A3") {
									$userdata[$data["JobSubmitLogon"]]["a3_bw"]=$userdata[$data["JobSubmitLogon"]]["a3_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
									$userdata[$data["JobSubmitLogon"]]["a3_clr"]=$userdata[$data["JobSubmitLogon"]]["a3_clr"]+$data["TrackingColorPageCount"];
									$userdata[$data["JobSubmitLogon"]]["a3_sheets"]=$userdata[$data["JobSubmitLogon"]]["a3_sheets"]+$data["JobSheetCount"];
									$userdata[$data["JobSubmitLogon"]]["totalcost"]=$userdata[$data["JobSubmitLogon"]]["totalcost"]+$data["Price"];
								} else {
									$userdata[$data["JobSubmitLogon"]]["other_bw"]=$userdata[$data["JobSubmitLogon"]]["other_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
									$userdata[$data["JobSubmitLogon"]]["other_clr"]=$userdata[$data["JobSubmitLogon"]]["other_clr"]+$data["TrackingColorPageCount"];
									$userdata[$data["JobSubmitLogon"]]["other_sheets"]=$userdata[$data["JobSubmitLogon"]]["other_sheets"]+$data["JobSheetCount"];
									$userdata[$data["JobSubmitLogon"]]["totalcost"]=$userdata[$data["JobSubmitLogon"]]["totalcost"]+$data["Price"];
								}
							}
			odbc_close($conn);
		consolewrite("Generating output ...");
			$outputdata="Username;Name;A4 BW;A4 Color;A3 BW;A3 Color;Other BW;Other Color;A4 Sheets;A3 Sheets;Other Sheets;Cost\r\n";
			foreach($userdata as $key => $value) {
				$outputdata.=$key.";";
				$outputdata.=$userdata[$key]["userfullname"].";";
				$outputdata.=$userdata[$key]["a4_bw"].";";
				$outputdata.=$userdata[$key]["a4_clr"].";";
				$outputdata.=$userdata[$key]["a3_bw"].";";
				$outputdata.=$userdata[$key]["a3_clr"].";";
				$outputdata.=$userdata[$key]["other_bw"].";";
				$outputdata.=$userdata[$key]["other_clr"].";";
				$outputdata.=$userdata[$key]["a4_sheets"].";";
				$outputdata.=$userdata[$key]["a3_sheets"].";";
				$outputdata.=$userdata[$key]["other_sheets"].";";
				$outputdata.=number_format(trim($userdata[$key]["totalcost"]),2,",","");
				$outputdata.="\r\n";
			}
		generateoutput("userprint",$outputdata,$outfile,$startdate,$enddate);
		consolewrite("Done!");
	}

	function rep_deviceprint($odbc,$startdate,$enddate,$outfile) {
		consolewrite("Generating device printing report ...");
			$conn=odbc_connect($odbc,"","");
				$devicesql=odbc_prepare($conn,"SELECT DeviceId,Name,Location FROM sccore.dbo.scDeviceInfo");
				consolewrite("Collecting data from safecom ...");
					odbc_execute($devicesql);
						consolewrite("Compiles data ...");
							$devicedata=array();
								while($data=odbc_fetch_array($devicesql)) {
									if(!array_key_exists($data["DeviceId"],$devicedata)) {
										$devicedata[$data["DeviceId"]]["name"]=$data["Name"];
										$devicedata[$data["DeviceId"]]["location"]=$data["Location"];
										$devicedata[$data["DeviceId"]]["status"]="Active";
										$devicedata[$data["DeviceId"]]["push_a4_bw"]=0;
										$devicedata[$data["DeviceId"]]["push_a4_clr"]=0;
										$devicedata[$data["DeviceId"]]["push_a3_bw"]=0;
										$devicedata[$data["DeviceId"]]["push_a3_clr"]=0;
										$devicedata[$data["DeviceId"]]["push_other_bw"]=0;
										$devicedata[$data["DeviceId"]]["push_other_clr"]=0;
										$devicedata[$data["DeviceId"]]["pull_a4_bw"]=0;
										$devicedata[$data["DeviceId"]]["pull_a4_clr"]=0;
										$devicedata[$data["DeviceId"]]["pull_a3_bw"]=0;
										$devicedata[$data["DeviceId"]]["pull_a3_clr"]=0;
										$devicedata[$data["DeviceId"]]["pull_other_bw"]=0;
										$devicedata[$data["DeviceId"]]["pull_other_clr"]=0;
										$devicedata[$data["DeviceId"]]["copy_a4_bw"]=0;
										$devicedata[$data["DeviceId"]]["copy_a4_clr"]=0;
										$devicedata[$data["DeviceId"]]["copy_a3_bw"]=0;
										$devicedata[$data["DeviceId"]]["copy_a3_clr"]=0;
										$devicedata[$data["DeviceId"]]["copy_other_bw"]=0;
										$devicedata[$data["DeviceId"]]["copy_other_clr"]=0;
										$devicedata[$data["DeviceId"]]["a4_sheets"]=0;
										$devicedata[$data["DeviceId"]]["a3_sheets"]=0;
										$devicedata[$data["DeviceId"]]["other_sheets"]=0;
										$devicedata[$data["DeviceId"]]["totalcost"]=0;
									}
								}
				$sql=odbc_prepare($conn,"SELECT DeviceId,DeviceName,DeviceLocation,TrackingPageCount,JobType,JobPageFormat,Price,TrackingColorPageCount,JobSheetCount FROM sctracking.dbo.scTracking WHERE (JobType='1' OR JobType='2' OR JobType='3') AND (StartDateTime BETWEEN '".$startdate." 00:00:00' AND '".$enddate." 23:59:59')");
					odbc_execute($sql);
						while($data=odbc_fetch_array($sql)) {
							if(!array_key_exists($data["DeviceId"],$devicedata)) {
								$devicedata[$data["DeviceId"]]["name"]=$data["DeviceName"];
								$devicedata[$data["DeviceId"]]["location"]=$data["DeviceLocation"];
								$devicedata[$data["DeviceId"]]["status"]="Deleted";
								$devicedata[$data["DeviceId"]]["push_a4_bw"]=0;
								$devicedata[$data["DeviceId"]]["push_a4_clr"]=0;
								$devicedata[$data["DeviceId"]]["push_a3_bw"]=0;
								$devicedata[$data["DeviceId"]]["push_a3_clr"]=0;
								$devicedata[$data["DeviceId"]]["push_other_bw"]=0;
								$devicedata[$data["DeviceId"]]["push_other_clr"]=0;
								$devicedata[$data["DeviceId"]]["pull_a4_bw"]=0;
								$devicedata[$data["DeviceId"]]["pull_a4_clr"]=0;
								$devicedata[$data["DeviceId"]]["pull_a3_bw"]=0;
								$devicedata[$data["DeviceId"]]["pull_a3_clr"]=0;
								$devicedata[$data["DeviceId"]]["pull_other_bw"]=0;
								$devicedata[$data["DeviceId"]]["pull_other_clr"]=0;
								$devicedata[$data["DeviceId"]]["copy_a4_bw"]=0;
								$devicedata[$data["DeviceId"]]["copy_a4_clr"]=0;
								$devicedata[$data["DeviceId"]]["copy_a3_bw"]=0;
								$devicedata[$data["DeviceId"]]["copy_a3_clr"]=0;
								$devicedata[$data["DeviceId"]]["copy_other_bw"]=0;
								$devicedata[$data["DeviceId"]]["copy_other_clr"]=0;
								$devicedata[$data["DeviceId"]]["a4_sheets"]=0;
								$devicedata[$data["DeviceId"]]["a3_sheets"]=0;
								$devicedata[$data["DeviceId"]]["other_sheets"]=0;
								$devicedata[$data["DeviceId"]]["totalcost"]=0;
							}
							if($data["JobType"]=="1") {
								if($data["JobPageFormat"]=="A4") {
									$devicedata[$data["DeviceId"]]["push_a4_bw"]=$devicedata[$data["DeviceId"]]["push_a4_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
									$devicedata[$data["DeviceId"]]["push_a4_clr"]=$devicedata[$data["DeviceId"]]["push_a4_clr"]+$data["TrackingColorPageCount"];
									$devicedata[$data["DeviceId"]]["a4_sheets"]=$devicedata[$data["DeviceId"]]["a4_sheets"]+$data["JobSheetCount"];
									$devicedata[$data["DeviceId"]]["totalcost"]=$devicedata[$data["DeviceId"]]["totalcost"]+$data["Price"];
								} elseif($data["JobPageFormat"]=="A3") {
									$devicedata[$data["DeviceId"]]["push_a3_bw"]=$devicedata[$data["DeviceId"]]["push_a3_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
									$devicedata[$data["DeviceId"]]["push_a3_clr"]=$devicedata[$data["DeviceId"]]["push_a3_clr"]+$data["TrackingColorPageCount"];
									$devicedata[$data["DeviceId"]]["a3_sheets"]=$devicedata[$data["DeviceId"]]["a3_sheets"]+$data["JobSheetCount"];
									$devicedata[$data["DeviceId"]]["totalcost"]=$devicedata[$data["DeviceId"]]["totalcost"]+$data["Price"];
								} else {
									$devicedata[$data["DeviceId"]]["push_other_bw"]=$devicedata[$data["DeviceId"]]["push_other_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
									$devicedata[$data["DeviceId"]]["push_other_clr"]=$devicedata[$data["DeviceId"]]["push_other_clr"]+$data["TrackingColorPageCount"];
									$devicedata[$data["DeviceId"]]["other_sheets"]=$devicedata[$data["DeviceId"]]["other_sheets"]+$data["JobSheetCount"];
									$devicedata[$data["DeviceId"]]["totalcost"]=$devicedata[$data["DeviceId"]]["totalcost"]+$data["Price"];
								}
							}
							if($data["JobType"]=="2") {
								if($data["JobPageFormat"]=="A4") {
									$devicedata[$data["DeviceId"]]["pull_a4_bw"]=$devicedata[$data["DeviceId"]]["pull_a4_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
									$devicedata[$data["DeviceId"]]["pull_a4_clr"]=$devicedata[$data["DeviceId"]]["pull_a4_clr"]+$data["TrackingColorPageCount"];
									$devicedata[$data["DeviceId"]]["a4_sheets"]=$devicedata[$data["DeviceId"]]["a4_sheets"]+$data["JobSheetCount"];
									$devicedata[$data["DeviceId"]]["totalcost"]=$devicedata[$data["DeviceId"]]["totalcost"]+$data["Price"];
								} elseif($data["JobPageFormat"]=="A3") {
									$devicedata[$data["DeviceId"]]["pull_a3_bw"]=$devicedata[$data["DeviceId"]]["pull_a3_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
									$devicedata[$data["DeviceId"]]["pull_a3_clr"]=$devicedata[$data["DeviceId"]]["pull_a3_clr"]+$data["TrackingColorPageCount"];
									$devicedata[$data["DeviceId"]]["a3_sheets"]=$devicedata[$data["DeviceId"]]["a3_sheets"]+$data["JobSheetCount"];
									$devicedata[$data["DeviceId"]]["totalcost"]=$devicedata[$data["DeviceId"]]["totalcost"]+$data["Price"];
								} else {
									$devicedata[$data["DeviceId"]]["pull_other_bw"]=$devicedata[$data["DeviceId"]]["pull_other_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
									$devicedata[$data["DeviceId"]]["pull_other_clr"]=$devicedata[$data["DeviceId"]]["pull_other_clr"]+$data["TrackingColorPageCount"];
									$devicedata[$data["DeviceId"]]["other_sheets"]=$devicedata[$data["DeviceId"]]["other_sheets"]+$data["JobSheetCount"];
									$devicedata[$data["DeviceId"]]["totalcost"]=$devicedata[$data["DeviceId"]]["totalcost"]+$data["Price"];
								}
							}
							if($data["JobType"]=="3") {
								if($data["JobPageFormat"]=="A4") {
									$devicedata[$data["DeviceId"]]["copy_a4_bw"]=$devicedata[$data["DeviceId"]]["copy_a4_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
									$devicedata[$data["DeviceId"]]["copy_a4_clr"]=$devicedata[$data["DeviceId"]]["copy_a4_clr"]+$data["TrackingColorPageCount"];
									$devicedata[$data["DeviceId"]]["a4_sheets"]=$devicedata[$data["DeviceId"]]["a4_sheets"]+$data["JobSheetCount"];
									$devicedata[$data["DeviceId"]]["totalcost"]=$devicedata[$data["DeviceId"]]["totalcost"]+$data["Price"];
								} elseif($data["JobPageFormat"]=="A3") {
									$devicedata[$data["DeviceId"]]["copy_a3_bw"]=$devicedata[$data["DeviceId"]]["copy_a3_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
									$devicedata[$data["DeviceId"]]["copy_a3_clr"]=$devicedata[$data["DeviceId"]]["copy_a3_clr"]+$data["TrackingColorPageCount"];
									$devicedata[$data["DeviceId"]]["a3_sheets"]=$devicedata[$data["DeviceId"]]["a3_sheets"]+$data["JobSheetCount"];
									$devicedata[$data["DeviceId"]]["totalcost"]=$devicedata[$data["DeviceId"]]["totalcost"]+$data["Price"];
								} else {
									$devicedata[$data["DeviceId"]]["copy_other_bw"]=$devicedata[$data["DeviceId"]]["copy_other_bw"]+$data["TrackingPageCount"]-$data["TrackingColorPageCount"];
									$devicedata[$data["DeviceId"]]["copy_other_clr"]=$devicedata[$data["DeviceId"]]["copy_other_clr"]+$data["TrackingColorPageCount"];
									$devicedata[$data["DeviceId"]]["other_sheets"]=$devicedata[$data["DeviceId"]]["other_sheets"]+$data["JobSheetCount"];
									$devicedata[$data["DeviceId"]]["totalcost"]=$devicedata[$data["DeviceId"]]["totalcost"]+$data["Price"];
								}
							}
						}
			odbc_close($conn);
		consolewrite("Generating output ...");
			$outputdata="Device ID;Device Name;Location;Status;Pushprint A4 BW;Pushprint A4 Color;Pushprint A3 BW;Pushprint A3 Color;Pushprint Other BW;Pushprint Other Color;Pullprint A4 BW;Pullprint A4 Color;Pullprint A3 BW;Pullprint A3 Color;Pullprint Other BW;Pullprint Other Color;Copy A4 BW;Copy A4 Color;Copy A3 BW;Copy A3 Color;Copy Other BW;Copy Other Color;A4 Sheets;A3 Sheets;Other Sheets;Cost\r\n";
			foreach($devicedata as $key => $value) {
				$outputdata.=$key.";";
				$outputdata.=$devicedata[$key]["name"].";";
				$outputdata.=$devicedata[$key]["location"].";";
				$outputdata.=$devicedata[$key]["status"].";";
				$outputdata.=$devicedata[$key]["push_a4_bw"].";";
				$outputdata.=$devicedata[$key]["push_a4_clr"].";";
				$outputdata.=$devicedata[$key]["push_a3_bw"].";";
				$outputdata.=$devicedata[$key]["push_a3_clr"].";";
				$outputdata.=$devicedata[$key]["push_other_bw"].";";
				$outputdata.=$devicedata[$key]["push_other_clr"].";";
				$outputdata.=$devicedata[$key]["pull_a4_bw"].";";
				$outputdata.=$devicedata[$key]["pull_a4_clr"].";";
				$outputdata.=$devicedata[$key]["pull_a3_bw"].";";
				$outputdata.=$devicedata[$key]["pull_a3_clr"].";";
				$outputdata.=$devicedata[$key]["pull_other_bw"].";";
				$outputdata.=$devicedata[$key]["pull_other_clr"].";";
				$outputdata.=$devicedata[$key]["copy_a4_bw"].";";
				$outputdata.=$devicedata[$key]["copy_a4_clr"].";";
				$outputdata.=$devicedata[$key]["copy_a3_bw"].";";
				$outputdata.=$devicedata[$key]["copy_a3_clr"].";";
				$outputdata.=$devicedata[$key]["copy_other_bw"].";";
				$outputdata.=$devicedata[$key]["copy_other_clr"].";";
				$outputdata.=$devicedata[$key]["a4_sheets"].";";
				$outputdata.=$devicedata[$key]["a3_sheets"].";";
				$outputdata.=$devicedata[$key]["other_sheets"].";";
				$outputdata.=number_format(trim($devicedata[$key]["totalcost"]),2,",","");
				$outputdata.="\r\n";
			}
		generateoutput("deviceprint",$outputdata,$outfile,$startdate,$enddate);
		consolewrite("Done!");
	}

	function rep_devicetracking($odbc,$deviceid,$startdate,$enddate,$outfile) {
		consolewrite("Generating device tracking report ...");
			$conn=odbc_connect($odbc,"","");
				$sql=odbc_prepare($conn,"SELECT StartDateTime,EndDateTime,ParserPageCount,DriverPageCount,TrackingPageCount,UserLogon,JobName,JobDateTime,JobType,JobSubmitLogon,JobSize,JobDriverName,JobPageFormat,JobIsDuplex,JobIsColor,Price,PageCountModel,TrackingColorPageCount,PMQueueName,PMPortName,UserCostCode,JobSheetCount FROM sctracking.dbo.scTracking WHERE DeviceId='".$deviceid."' AND (StartDateTime BETWEEN '".$startdate." 00:00:00' AND '".$enddate." 23:59:59')");
				consolewrite("Collecting data from safecom ...");
					odbc_execute($sql);
						consolewrite("Generating output ...");
							$outputdata="StartDateTime;EndDateTime;ParserPageCount;DriverPageCount;TrackingPageCount;UserLogon;JobName;JobDateTime;JobType;JobSubmitLogon;JobSize;JobDriverName;JobPageFormat;JobIsDuplex;JobIsColor;Price;PageCountModel;TrackingColorPageCount;PMQueueName;PMPortName;UserCostCode;JobSheetCount\r\n";
								while($data=odbc_fetch_array($sql)) {
									$outputdata.=$data["StartDateTime"].";".$data["EndDateTime"].";".$data["ParserPageCount"].";".$data["DriverPageCount"].";".$data["TrackingPageCount"].";".$data["UserLogon"].";".$data["JobName"].";".$data["JobDateTime"].";".$data["JobType"].";".$data["JobSubmitLogon"].";".$data["JobSize"].";".$data["JobDriverName"].";".$data["JobPageFormat"].";".$data["JobIsDuplex"].";".$data["JobIsColor"].";".number_format(trim($data["Price"]),2,",","").";".$data["PageCountModel"].";".$data["TrackingColorPageCount"].";".$data["PMQueueName"].";".$data["PMPortName"].";".$data["UserCostCode"].";".$data["JobSheetCount"]."\r\n";
								}
			odbc_close($conn);
		generateoutput("devicetracking",$outputdata,$outfile,$startdate,$enddate);
		consolewrite("Done!");
	}

	// get options
	$opts=getopt($options);

	// script
	echo "        __           _______ ______ \n";
	echo ".-----.|  |--.-----.|     __|   __ \\\n";
	echo "|  _  ||     |  _  ||__     |      <\n";
	echo "|   __||__|__|   __||_______|___|__|\n";
	echo "|__|         |__|                   \n";
	echo "\nVersion: 0.3.0-trunk_160912\n";
	echo "Author: Andreas (andreas@dotdeas.se)\n\n";
	if(isset($opts["h"])) {
		printhelp();
		exit;
	}
	if(isset($opts["d"]) && $opts["d"]<>"") {
		checkodbc($opts["d"]);
	} else {
		echo "No odbc connection defined!\n";
		exit;
	}
	if(isset($opts["o"]) && $opts["o"]<>"") {
		$outfile=$opts["o"];
	} else {
		$outfile="";
	}
	if(isset($opts["c"]) && $opts["c"]<>"") {
		$costcode=$opts["c"];
	} else {
		$costcode="";
	}
	if(isset($opts["r"]) && $opts["r"]<>"") {
		if($opts["r"]=="ccprinting") {
			$datedata=explode(";",checkstartend($options));
			rep_ccprinting($opts["d"],$datedata[0],$datedata[1],$outfile);
		}
		if($opts["r"]=="ccprintingdetailed") {
			$datedata=explode(";",checkstartend($options));
			rep_ccprintingdetailed($opts["d"],$datedata[0],$datedata[1],$outfile);
		}
		if($opts["r"]=="ccprintingless") {
			$datedata=explode(";",checkstartend($options));
			rep_ccprintingless($opts["d"],$datedata[0],$datedata[1],$outfile);
		}
		if($opts["r"]=="ccuserprint") {
			$datedata=explode(";",checkstartend($options));
			rep_ccuserprint($opts["d"],$costcode,$datedata[0],$datedata[1],$outfile);
		}
		if($opts["r"]=="userprint") {
			$datedata=explode(";",checkstartend($options));
			rep_userprint($opts["d"],$opts["u"],$datedata[0],$datedata[1],$outfile);
		}
		if($opts["r"]=="deviceprint") {
			$datedata=explode(";",checkstartend($options));
			rep_deviceprint($opts["d"],$datedata[0],$datedata[1],$outfile);
		}
		if($opts["r"]=="devicetracking") {
			$datedata=explode(";",checkstartend($options));
			rep_devicetracking($opts["d"],$opts["i"],$datedata[0],$datedata[1],$outfile);
		}
	} else {
		echo "No report selected!\n";
		exit;
	}