<?php
	// settings
	mb_internal_encoding("utf-8");
	date_default_timezone_set("Europe/Stockholm");
	ini_set("memory_limit","256M");
	set_time_limit(0);
	set_error_handler("internalerror");
	$options="d:c:o:r:s:e:h::";

	// functions
	function consolewrite($input) {
		print("[".date("Y-m-d H:i:s")."] ".$input."\n");
	}

	function internalerror($errno,$errstr) {
		consolewrite("Error: [".$errno."] ".$errstr);
	}

	function checkstartend($options) {
		$opts=getopt($options);
		if(!isset($opts["s"]) || $opts["s"]=="") {
			consolewrite("Missing startdate, aborting!");
			exit;
		}
		if(!isset($opts["e"]) || $opts["e"]=="") {
			consolewrite("Missing enddate, aborting!");
			exit;
		}
	}

	function printhelp() {
		echo "Usage: php phpsc.php [OPTION] ...\n\n";
		echo "  -h    print this help\n";
		echo "  -d    odbc connection name\n";
		echo "  -c    currency\n";
		echo "  -o    output filename\n";
		echo "  -r    report to use (see list below)\n";
		echo "  -s    startdate (yyyy-mm-dd)\n";
		echo "  -e    enddate (yyyy-mm-dd)\n";
		echo "\n";
		echo "Reports:\n";
		echo "  1 - Cost code printing\n";
		echo "  2 - Cost code printing (detailed)\n";
		echo "  3 - Cost code printing (less)\n";
	}

	// report functions
	function report_1($odbc,$startdate,$enddate,$outfile,$currency) {
		consolewrite("Generating report 1 ...");
			$conn=odbc_connect($odbc,"","");
				$sql=odbc_prepare($conn,"SELECT TrackingPageCount,JobType,JobPageFormat,Price,TrackingColorPageCount,UserCostCode,JobSheetCount FROM sctracking.dbo.scTracking WHERE (JobType='1' OR JobType='2' OR JobType='3') AND (StartDateTime BETWEEN '".$startdate." 00:00:00' AND '".$enddate." 23:59:59')");
				consolewrite("Collecting data from safecom database ...");
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
			$outputdata="Costcode,Print A4 BW,Print A4 Color,Print A3 BW,Print A3 Color,Print Other BW,Print Other Color,Copy A4 BW,Copy A4 Color,Copy A3 BW,Copy A3 Color,Copy Other BW,Copy Other Color,A4 Sheets,A3 Sheets,Other Sheets,Cost\r\n";
			foreach($costcodedata as $key => $value) {
				if($key=="") {
					$outputdata.="N/A,";
				} else {
					$outputdata.=$key.",";
				}
				$outputdata.=$costcodedata[$key]["print_a4_bw"].",";
				$outputdata.=$costcodedata[$key]["print_a4_clr"].",";
				$outputdata.=$costcodedata[$key]["print_a3_bw"].",";
				$outputdata.=$costcodedata[$key]["print_a3_clr"].",";
				$outputdata.=$costcodedata[$key]["print_other_bw"].",";
				$outputdata.=$costcodedata[$key]["print_other_clr"].",";
				$outputdata.=$costcodedata[$key]["copy_a4_bw"].",";
				$outputdata.=$costcodedata[$key]["copy_a4_clr"].",";
				$outputdata.=$costcodedata[$key]["copy_a3_bw"].",";
				$outputdata.=$costcodedata[$key]["copy_a3_clr"].",";
				$outputdata.=$costcodedata[$key]["copy_other_bw"].",";
				$outputdata.=$costcodedata[$key]["copy_other_clr"].",";
				$outputdata.=$costcodedata[$key]["a4_sheets"].",";
				$outputdata.=$costcodedata[$key]["a3_sheets"].",";
				$outputdata.=$costcodedata[$key]["other_sheets"].",";
				$outputdata.=trim($costcodedata[$key]["totalcost"]." ".$currency);
				$outputdata.="\r\n";
			}
		consolewrite("Saving report file ...");
			if($outfile<>"") {
				$filename=str_replace(".csv","",$outfile).".csv";
			} else {
				$filename="costcode-".$startdate."_to_".$enddate.".csv";
			}
			$outfile=fopen($filename,"w");
			fwrite($outfile,$outputdata);
			fclose($outfile);
		consolewrite("Done!");
	}

	function report_2($odbc,$startdate,$enddate,$outfile,$currency) {
		consolewrite("Generating report 2 ...");
			$conn=odbc_connect($odbc,"","");
				$sql=odbc_prepare($conn,"SELECT TrackingPageCount,JobType,JobPageFormat,Price,TrackingColorPageCount,UserCostCode,JobSheetCount FROM sctracking.dbo.scTracking WHERE (JobType='1' OR JobType='2' OR JobType='3') AND (StartDateTime BETWEEN '".$startdate." 00:00:00' AND '".$enddate." 23:59:59')");
				consolewrite("Collecting data from safecom database ...");
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
			$outputdata="Costcode,Pushprint A4 BW,Pushprint A4 Color,Pushprint A3 BW,Pushprint A3 Color,Pushprint Other BW,Pushprint Other Color,Pullprint A4 BW,Pullprint A4 Color,Pullprint A3 BW,Pullprint A3 Color,Pullprint Other BW,Pullprint Other Color,Copy A4 BW,Copy A4 Color,Copy A3 BW,Copy A3 Color,Copy Other BW,Copy Other Color,A4 Sheets,A3 Sheets,Other Sheets,Cost\r\n";
			foreach($costcodedata as $key => $value) {
				if($key=="") {
					$outputdata.="N/A,";
				} else {
					$outputdata.=$key.",";
				}
				$outputdata.=$costcodedata[$key]["push_a4_bw"].",";
				$outputdata.=$costcodedata[$key]["push_a4_clr"].",";
				$outputdata.=$costcodedata[$key]["push_a3_bw"].",";
				$outputdata.=$costcodedata[$key]["push_a3_clr"].",";
				$outputdata.=$costcodedata[$key]["push_other_bw"].",";
				$outputdata.=$costcodedata[$key]["push_other_clr"].",";
				$outputdata.=$costcodedata[$key]["pull_a4_bw"].",";
				$outputdata.=$costcodedata[$key]["pull_a4_clr"].",";
				$outputdata.=$costcodedata[$key]["pull_a3_bw"].",";
				$outputdata.=$costcodedata[$key]["pull_a3_clr"].",";
				$outputdata.=$costcodedata[$key]["pull_other_bw"].",";
				$outputdata.=$costcodedata[$key]["pull_other_clr"].",";
				$outputdata.=$costcodedata[$key]["copy_a4_bw"].",";
				$outputdata.=$costcodedata[$key]["copy_a4_clr"].",";
				$outputdata.=$costcodedata[$key]["copy_a3_bw"].",";
				$outputdata.=$costcodedata[$key]["copy_a3_clr"].",";
				$outputdata.=$costcodedata[$key]["copy_other_bw"].",";
				$outputdata.=$costcodedata[$key]["copy_other_clr"].",";
				$outputdata.=$costcodedata[$key]["a4_sheets"].",";
				$outputdata.=$costcodedata[$key]["a3_sheets"].",";
				$outputdata.=$costcodedata[$key]["other_sheets"].",";
				$outputdata.=trim($costcodedata[$key]["totalcost"]." ".$currency);
				$outputdata.="\r\n";
			}
		consolewrite("Saving report file ...");
			if($outfile<>"") {
				$filename=str_replace(".csv","",$outfile).".csv";
			} else {
				$filename="costcode_detailed-".$startdate."_to_".$enddate.".csv";
			}
			$outfile=fopen($filename,"w");
			fwrite($outfile,$outputdata);
			fclose($outfile);
		consolewrite("Done!");
	}

	function report_3($odbc,$startdate,$enddate,$outfile,$currency) {
		consolewrite("Generating report 3 ...");
			$conn=odbc_connect($odbc,"","");
				$sql=odbc_prepare($conn,"SELECT TrackingPageCount,JobType,JobPageFormat,Price,TrackingColorPageCount,UserCostCode,JobSheetCount FROM sctracking.dbo.scTracking WHERE (JobType='1' OR JobType='2' OR JobType='3') AND (StartDateTime BETWEEN '".$startdate." 00:00:00' AND '".$enddate." 23:59:59')");
				consolewrite("Collecting data from safecom database ...");
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
			$outputdata="Costcode,A4 BW,A4 Color,A3 BW,A3 Color,Other BW,Other Color,A4 Sheets,A3 Sheets,Other Sheets,Cost\r\n";
			foreach($costcodedata as $key => $value) {
				if($key=="") {
					$outputdata.="N/A,";
				} else {
					$outputdata.=$key.",";
				}
				$outputdata.=$costcodedata[$key]["a4_bw"].",";
				$outputdata.=$costcodedata[$key]["a4_clr"].",";
				$outputdata.=$costcodedata[$key]["a3_bw"].",";
				$outputdata.=$costcodedata[$key]["a3_clr"].",";
				$outputdata.=$costcodedata[$key]["other_bw"].",";
				$outputdata.=$costcodedata[$key]["other_clr"].",";
				$outputdata.=$costcodedata[$key]["a4_sheets"].",";
				$outputdata.=$costcodedata[$key]["a3_sheets"].",";
				$outputdata.=$costcodedata[$key]["other_sheets"].",";
				$outputdata.=trim($costcodedata[$key]["totalcost"]." ".$currency);
				$outputdata.="\r\n";
			}
		consolewrite("Saving report file ...");
			if($outfile<>"") {
				$filename=str_replace(".csv","",$outfile).".csv";
			} else {
				$filename="costcode_less-".$startdate."_to_".$enddate.".csv";
			}
			$outfile=fopen($filename,"w");
			fwrite($outfile,$outputdata);
			fclose($outfile);
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
	echo "\nVersion: 0.0.0-trunk_160715\n";
	echo "Author: Andreas (andreas@dotdeas.se)\n\n";
	if(isset($opts["h"])) {
		printhelp();
		exit;
	}
	if(isset($opts["o"]) && $opts["o"]<>"") {
		$outfile=$opts["o"];
	} else {
		$outfile="";
	}
	if(isset($opts["c"]) && $opts["c"]<>"") {
		$currency=$opts["c"];
	} else {
		$currency="";
	}
	if(isset($opts["r"]) && $opts["r"]<>"") {
		if($opts["r"]=="1") {
			checkstartend($options);
			report_1($opts["d"],$opts["s"],$opts["e"],$outfile,$currency);
		}
		if($opts["r"]=="2") {
			checkstartend($options);
			report_2($opts["d"],$opts["s"],$opts["e"],$outfile,$currency);
		}
		if($opts["r"]=="3") {
			checkstartend($options);
			report_3($opts["d"],$opts["s"],$opts["e"],$outfile,$currency);
		}
	} else {
		consolewrite("No report selected!");
		exit;
	}