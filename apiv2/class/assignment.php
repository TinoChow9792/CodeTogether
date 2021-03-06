<?php
require_once "mysql.php";
require_once "user.php";
require_once "file.php";
/**
 * assignment class.
 *
 * @extends mysql
 */
class assignment extends mysql{

	public $basePath;
	public $info;
	private $course_id;
	private $assignment_id;

    /**
     * __construct function.
     *
     * @access public
     * @param mixed $assignment_id
     * @return void
     */
    public function __construct($assignment_id = null) {
        parent::__construct();
        $this->basePath = dirname(__FILE__);
        $this->assignment_id = $assignment_id;
        //Individual Course
        if(!is_null($assignment_id)){
	        $this->info = $this->assignmentInfo($assignment_id);
	        $this->assignment_id = $assignment_id;
        }
    }

    private function rrmdir($dir){
		if(is_dir($dir)){
			$objects = scandir($dir);
			foreach ($objects as $object){
				if ($object != "." && $object != ".."){
					if (filetype($dir."/".$object) == "dir")
						$this->rrmdir($dir."/".$object);
					else
						unlink($dir."/".$object);
				}
			}
			reset($objects);
			rmdir($dir);
		}
	}
	
	public function groupID($assignment_id){
		if(user::isTeacher()) return;
		$user_id = user::authService()['user_id'];
		if(isset($assignment_id)){
			$sql = "SELECT AGS.group_id 
					FROM assignment_group_student AGS
					JOIN assignment_group AG ON AG.group_id = AGS.group_id
					WHERE AGS.student_id = '{$user_id}' 
					AND AG.assignment_id = '{$assignment_id}'";
			$result = $this->query($sql);
			return $result->fetch_assoc()['group_id'];
		}
	}
	
	public function groupList($assignment_id){
		if(user::isStudent()){
			$group_id = $this->groupID($assignment_id);
			$sql = "SELECT AG.group_id as group_id, U.name as name, S.name as school_name, U.user_id as user_id
					FROM assignment_group AG
					JOIN assignment_group_student AGS ON AG.group_id = AGS.group_id
					JOIN user U ON U.user_id = AGS.student_id
					JOIN school S ON S.school_id = U.school_id
					WHERE AG.assignment_id = '{$assignment_id}'
					AND AG.group_id = '{$group_id}'
					ORDER BY U.name ASC";			
		}else{
			$sql = "SELECT AG.group_id as group_id, U.name as name, S.name as school_name, U.user_id as user_id
					FROM assignment_group AG
					JOIN assignment_group_student AGS ON AG.group_id = AGS.group_id
					JOIN user U ON U.user_id = AGS.student_id
					JOIN school S ON S.school_id = U.school_id
					WHERE AG.assignment_id = '{$assignment_id}'
					ORDER BY U.name ASC";
		}
		$result = $this->query($sql);
		$data = array();
		while($row = $result->fetch_assoc()){
			$data[$row['group_id']][] = $row;
		}
		return $data;		
	}


    public function submitAssignment($group_id, $codeArray){
	    if(isset($group_id) && isset($codeArray) ){
		    /*	    
		    $sqlCheck = "SELECT code FROM assignment_group WHERE group_id = '{$group_id}'";
		    $codeCheck = $this->query($sqlCheck)->fetch_assoc()['code'];		   
		    
		    if($codeCheck == $code){
			    return $this->compileAssignment($group_id);
		    }	
		    */
			$file = "../code/". $group_id ."";
			if(file_exists($file)){
				$this->rrmdir($file);
			}
			mkdir($file);
			mkdir($file . "/debug");	    
		    foreach($codeArray as $editor_id => $code){
		    	$excapedCode = $this->escape($code);
		    	$sql = "UPDATE assignment_group_code SET code = '{$excapedCode}' WHERE editor = '{$editor_id}'";
				$this->query($sql);
				if($code !="")
					file_put_contents($file . "/".$editor_id.".java", $code);	
		    }
			$this->makeFileHistory($group_id, $codeArray);
			return $this->compileAssignment($group_id);
		}
    }

    public function compileAssignment($group_id){
	    if(isset($group_id)){
		    $file = "../code/". $group_id ."";
			if(count(glob($file . "/*.java")) > 0){
				$compile = shell_exec("javac $file/*.java 2> $file/debug/compile.txt");
				$compileLog = file_get_contents("$file/debug/compile.txt");
				return $compileLog;
			}
	    }
    }
    
    public function runAgainstTestCase($group_id){
		if(isset($group_id)){
		$path = "/var/www/html2/code";
		$folder = "/var/www/html2/code/".$_POST['group_id'];
		$testcases = "/var/www/html2/code/".$_POST['group_id']."/testcases";
		//$testcases1 = "../code/".$_POST['group_id']."/testcases";
			//if(file_exists($testcases1)){
			//	$this->rrmdir($testcases1);
			//}
			//mkdir($testcases1);
			$sql = "SELECT * FROM assignment_testcase AT JOIN assignment_group as AG ON AG.assignment_id = AT.assignment_id where AG.group_id = '{$group_id}'";
			$result = $this->query($sql);
			$inputs = array();
			$expectedOutputs = array();
			$comments = array();
			$descriptions = array();
			$types = array();
			$ids = array();
			$counter = 0;
			while($row = $result->fetch_assoc()){
				$inputs[] = $row['input'];
				$expectedOutputs[] = $row['output'];
				//$comments[] = $row['comment'];
				$descriptions[] = $row['description'];
				$types[] = $row['type'];
				$ids[] = $row['testcase_id'];
				//file_put_contents($testcases . "/inputs.txt", $row['input']);	
				//file_put_contents($testcases . "/output.txt", $row['output']);	
				//file_put_contents($testcases . "/comment.txt", $row['comment']);	
				//file_put_contents($testcases . "/description.txt", $row['description']);
			}	
			
				$command = "sh $path/mainClass.sh $folder";
				$mainClass = shell_exec($command);
				$mainClass = explode("@@@@@@@@@@", $mainClass);
				$mainClass = $mainClass[1];
				if($mainClass == ""){
					echo "Error: Cannot find Java Main Class<br />";
					}
				$mainClass = explode("/", $mainClass);
				$mainClass = $mainClass[sizeof($mainClass)-1];
				$testcaseResults = array();
				$counter = 0;
				foreach($inputs as $input){
					$temp = $expectedOutputs[$counter];
					$commandRun = "sh $path/testcase.sh '$folder' '$mainClass' '$input' '$temp' ";
					$run = shell_exec($commandRun);
					switch($run){
						case "TIMEOUT":
							$testcaseResults[] = array('id' => $ids[$counter],'resultType' => "TIMEOUT",'description' => $descriptions[$counter],'type' => $types[$counter]);
						break;
						case "PASS":
							$testcaseResults[] = array('id' => $ids[$counter],'resultType' => "PASS",'description' => $descriptions[$counter],'type' => $types[$counter]);
						break;
						case "FAIL":
							$testcaseResults[] = array('id' => $ids[$counter],'resultType' => "FAIL",'description' => $descriptions[$counter],'type' => $types[$counter]);
						break;
						default:
							$dummy = $run;
							break;
						
					}
					$sql2 = "INSERT INTO testcase_data (group_id, result, testcase_id) VALUES ('{$group_id}', '{$run}', '{$ids[$counter]}')";
					$this->query($sql2);
					$counter++;
				}
				//return json_encode(array('id' => $ids[0],'inputs' => $inputs[0]));
				return json_encode($testcaseResults);
				//return $dummy;
			
		}
    }
    
    //DATAMINING
    public function generateTrainingData($assignment_id,$input,$expectedOutput){
		if(isset($assignment_id)){
			$path = "/var/www/html2/datamining/";
			//if(file_exists($path)){
			//	$this->rrmdir($path);
			//}
			//mkdir($path);
			unlink($path."test.arff");
			$sql = "SELECT DISTINCT(type) FROM assignment_testcase WHERE assignment_id = '{$assignment_id}'";
			$result = $this->query($sql);
			$types = array();
			while($row = $result->fetch_assoc()){
				$types[] = $row['type'];
			}
			$sql = "SELECT * FROM assignment_testcase WHERE assignment_id = '{$assignment_id}'";
			$result = $this->query($sql);
			$testcaseIDs = array();
			$data = array();
			if($result->num_rows != 0){
				while($row = $result->fetch_assoc()){
					$data[] = $row;
				}
				$sql = "SELECT DISTINCT(group_id) FROM testcase_data WHERE testcase_id = '{$data[0]['testcase_id']}'";
				$result = $this->query($sql);
				$counter = 0;
				while($row = $result->fetch_assoc()){
					$temp = $row['group_id'];
					$counter++;
				}
				$content = "";
					$content .= "@RELATION testcase\n";
				for($i = 1; $i <= $counter ; $i++){
					$content .= "@ATTRIBUTE group" .$i. " REAL\n";
				}
				$content .= "@ATTRIBUTE class {";
				for($i = 0;$i < sizeof($types) ; $i++){
					$content .= $types[$i];
					if($i != (sizeof($types) -1 )){
						$content .= ",";
						}
				}
				$content .= "}\n";
				$content .= "@DATA\n";
				$groupIDs = null;
				foreach($data as $testcase){
					$class = $testcase['type'];
					$testcase_id = $testcase['testcase_id'];
					$sql = "SELECT MAX(data_id),group_id,result FROM testcase_data WHERE testcase_id = '{$testcase_id}' GROUP BY group_id ORDER BY group_id ASC";
					$result = $this->query($sql);
					$groupIDs = array();
					$results = array();
					while($row = $result->fetch_assoc()){
						$groupIDs[] = $row['group_id'];
						$results[] = $row['result'];
						$counter = 0;
						switch($row['result']){
							case "PASS":
								$content .= "1,";
							break;
							case "FAIL":
								$content .= "0,";
							break;
							case "TIMEOUT":
								$content .= "0.5,";
							break;
							default:
							break;
						}
						error_log("Test case ID : ".$testcase_id." Group : ".$row['group_id']." Result : ".$row['result'],0);
							$counter++;
						
					}
					$content .= $class;
					$content .= "\n";
				}
				$content .= "%\n";
				$content .= "%\n";
				$content .= "%\n";
				$file = $path."test.arff";
				$bool = file_put_contents($file,$content);
				$this->generateNewTestCase($assignment_id,$input,$groupIDs,$expectedOutput,$types);
				
			}
		
		}
		
	}
	public function generateNewTestCase($assignment_id,$input,$groupIDs,$expectedOutput,$types){
		if(isset($assignment_id)){
			$path = "/var/www/html2/datamining/";
			unlink($path."unlabeled.arff");
				$content = "";
					$content .= "@RELATION testcase\n";
				for($i = 1; $i <= sizeof($groupIDs) ; $i++){
					$content .= "@ATTRIBUTE group" .$i. " REAL\n";
				}
				$content .= "@ATTRIBUTE class {";
				for($i = 0;$i < sizeof($types) ; $i++){
					$content .= $types[$i];
					if($i != (sizeof($types) -1 )){
						$content .= ",";
						}
				}
				$content .= "}\n";
				$content .= "@DATA\n";
				$input .= "\n";
						error_log("New Test Case Input : ".$input,0);
			foreach($groupIDs as $groupID){
					error_log("groupID : ".$groupID,0);
				$path = "/var/www/html2/code";
				$folder = "/var/www/html2/code/".$groupID;
				$command = "sh $path/mainClass.sh $folder";
				$mainClass = shell_exec($command);
				$mainClass = explode("@@@@@@@@@@", $mainClass);
				$mainClass = $mainClass[1];
				if($mainClass == ""){
					echo "Error: Cannot find Java Main Class<br />";
					}
				$mainClass = explode("/", $mainClass);
				$mainClass = $mainClass[sizeof($mainClass)-1];
				$expectedOutput = str_replace("\n", "", $expectedOutput);
				$commandRun = "sh $path/testcase.sh '$folder' '$mainClass' '$input' '$expectedOutput' ";
					$run = shell_exec($commandRun);
					switch($run){
						case "TIMEOUT":
							$content .= "0.5,";
						break;
						case "PASS":
							$content .= "1,";
						break;
						case "FAIL":
							$content .= "0,";
						break;
						default:
							break;
						
					}
						error_log("New Test Case Group : ".$groupID." Result : ".$run,0);
				
			}
					$content .= "?\n";
					$content .= "%\n";
					$content .= "%\n";
					$content .= "%\n";
					$path = "/var/www/html2/datamining/";
					$file = $path."unlabeled.arff";
					$bool = file_put_contents($file,$content);
					error_log("unlabeled.arff PATH : ".$file,0);
					error_log("generate new test case : ".$bool,0);
			
		}
	}
	
	public function testcaseRanking($assignment_id){
		if(isset($assignment_id)){
			$sql = "SELECT DISTINCT(type) FROM assignment_testcase WHERE assignment_id = '{$assignment_id}'";
			$result = $this->query($sql);
			$testcases = array();
			//$testcaseIDs = array();
			while($row = $result->fetch_assoc()){
				$testcaseTypes[] = $row['type'];
				//$testcaseIDs[] = $row['testcase_id'];
			}
			//$rankingResult = array();
			//each type in the assingment
			//foreach($testcaseTypes as $testcaseType){
				$sql = "SELECT * FROM assignment_testcase WHERE assignment_id = '{$assignment_id}'";
				$result = $this->query($sql);
				//$testcaseIDsForEachType = array();
				//fetch test cases in a type
				while($row = $result->fetch_assoc()){
					//$testcaseIDsForEachType[] = $row['testcase_id'];
					$testcases[] = array('id'=>$row['testcase_id'],'type'=>$row['type'],'rate'=>"0");
				}
				//$counterForEachType = 0;
				//$avgForEachType = 0;
				
				//each testcase
				foreach($testcases as &$testcase){
					$sql = "SELECT DISTINCT(group_id) FROM testcase_data WHERE testcase_id = '{$testcase['id']}' ORDER BY group_id ASC";
					$result = $this->query($sql);
					$groupIDs = array();
					while($row = $result->fetch_assoc()){
						$groupIDs[] = $row['group_id'];
					}
					$groupAvg = 0;
					$groupCounter = 0;
					//each group of each testcase
					foreach($groupIDs as $groupID){
						$sql = "SELECT * FROM testcase_data WHERE testcase_id = '{$testcase['id']}' AND group_id = '{$groupID}' ORDER BY data_id ASC LIMIT 0,100";
						$result = $this->query($sql);
						$counter = 0;
						$avg = 0;
						$firstPassAt = null;
						//fetch history of a group
						while($row = $result->fetch_assoc()){
							switch($row['result']){
								case "PASS":
									if($firstPassAt == null){
										$firstPassAt = ($counter + 1);
										error_log("first pass at ".$firstPassAt);
										}
									//$avg += 1;
								break;
								case "FAIL":
									//$avg += 0;
								break;
								case "TIMEOUT":
									//$avg += 0.5;
								break;
								default:
									//$avg += 0;
								break;
							}
							$counter++;
							//end of loop of histories of a group
						}
						if($firstPassAt == null){
							$firstPassAt = "null";
							//error_log("first pass is null!! ");
						}else{
							$firstPassAt = ($firstPassAt - 1) / $counter;
							$firstPassAt = 100 - $firstPassAt * 100;
							//percent of successful attempt
							$groupAvg += $firstPassAt;
						}
						//$avg = $avg / $counter;
						//$groupAvg += $avg;
						$groupCounter++;
							error_log("group : ".$groupID." testcase : ".$testcase['id']." % of success attempt : ".$firstPassAt." counter : ".$counter);
					
						//end of loop of each group
					}
					$groupAvg = $groupAvg / $groupCounter;
					$testcase['rate'] = $groupAvg;
					//$avgForEachType += $groupAvg;
					//$counterForEachType++;
						
					//end of loop of a test case
				
				}
				//$avgForEachType = $avgForEachType / $counterForEachType;
				//$avgForEachType *= 100;
				//$rankingResult[] = array('type' => $testcaseType,'passRate' => $avgForEachType);
				
				// end of loop of a type
				
			//}
			//end of loop of all type
	function cmp($a,$b){
		if($a['rate'] == $b['rate']){
			return 0;
		}
		return ($a['rate'] > $b['rate']) ? -1 : 1;
	}
			uasort($testcases, "cmp");
			$tt = array();
			foreach($testcases as $t){
				$tt[] = $t;
			}
			//var_dump($tt); die();
			//die();
			//var_dump($testcases); die();
			return json_encode($tt);
			
		}
	}
    /*Depreciated
    public function testAssignment($group_id,$inputs){
	    if(isset($group_id) && isset($inputs)){
		    chop("",$inputs);
		    $file = "../code/". $group_id ."";
			file_put_contents($file . "/inputs.txt", $inputs);
			if(file_exists($file . "/assignment.java")){
				$compile = shell_exec("java $file/assignment $inputs  1> $file/debug/runtime.txt 2> $file/output/result.txt");
				if(file_exists($file."/debug/runtime.txt")){
					$runTimeLog = file_get_contents("$file/debug/runtime.txt");
					//$runTimeLog = "1";
					return $runTimeLog;
				}else{
					$result = file_get_contents("$file/output/result.txt");
					return $result;
				}
			}
		    
	    }
    }
    */
    
    /**
     * makeFileHistory function to save max 50 coding history
     * 
     * @access public
     * @param mixed $group_id
     * @param mixed $excapedCode
     * @return bool
     */
     	public function makeFileHistory($group_id, $codeArray){
	    $user_id = user::authService()['user_id'];
	    foreach($codeArray as $editor_id => $code){
		    $excapedCode = $this->escape($code);
		    if($excapedCode != ""){
	    		$sql = "INSERT INTO assignment_history (group_id, code, user_id,editor) VALUES ('{$group_id}', '{$excapedCode}', '{$user_id}','{$editor_id}')";
				$this->query($sql);
			}
	    }
	    $sql2 = "SELECT assignment_history_id as id FROM assignment_history  WHERE group_id = '{$group_id}' ORDER BY save_time DESC LIMIT 1 OFFSET 50";
	    $result = $this->query($sql2);
	    if($result->num_rows != 0){
		    $id = $result->fetch_assoc()['id'];
	    
			$sql3 = "DELETE FROM assignment_history WHERE group_id = '{$group_id}' AND assignment_history_id < '{$id}'";
			$this->query($sql3);
		}
	    
	    return true;
    }
     /*
    public function makeFileHistory($group_id, $excapedCode){
	    $user_id = user::authService()['user_id'];
	    
	    $sql = "INSERT INTO assignment_history (group_id, code, user_id) VALUES ('{$group_id}', '{$excapedCode}', '{$user_id}')";
	    $this->query($sql);
	    
	    $sql2 = "SELECT assignment_history_id as id FROM assignment_history  WHERE group_id = '{$group_id}' ORDER BY save_time DESC LIMIT 1 OFFSET 50";
	    $result = $this->query($sql2);
	    if($result->num_rows != 0){
		    $id = $result->fetch_assoc()['id'];
	    
			$sql3 = "DELETE FROM assignment_history WHERE group_id = '{$group_id}' AND assignment_history_id < '{$id}'";
			$this->query($sql3);
		}
	    
	    return true;
    }
    */
    
    
    /**
     * getFileHistory function.
     * 
     * @access public
     * @param mixed $group_id
     * @return json
     */
    public function getFileHistory($group_id, $assignment_history_id = null){	
	    if(is_null($assignment_history_id)){
		    $sql = "SELECT AH.assignment_history_id as assignment_history_id, AH.save_time as save_time, U.name as name
		    		FROM assignment_history AH 
		    		JOIN user U ON AH.user_id = U.user_id
		    		WHERE group_id = '{$group_id}'";
		    $result = $this->query($sql);
		    $data = array();
		    while($row = $result->fetch_assoc()){
			    $data[] = $row;
		    }
	    }else{
		    $sql = "SELECT code FROM assignment_history WHERE group_id = '{$group_id}' AND assignment_history_id = '{$assignment_history_id}' ";
		    $result = $this->query($sql);		    
		    $row = $result->fetch_assoc();
			$data = $row;		    		    
	    }
	    return json_encode($data);
    }
        


    /* createGroup function (To be implemented).
     *
     * @access public
     * @param mixed $assignment_id
     * @param mixed $num_per_group number of student per group
     * @return void
     */
    public function createGroup($assignment_id, $num_per_group){
        if(isset($assignment_id) && isset($num_per_group)){
            $sql = "SELECT U.user_id
                    FROM user U
                    JOIN assignment A ON A.assignment_id = {$assignment_id}
                    JOIN course C ON A.course_id = C.course_id
                    WHERE U.type = 0";
            $result = $this->query($sql);           
            $students = array();
            while($row = $result->fetch_assoc()){
                $students[] = $row['user_id'];
            }
            shuffle($students);
            $i = 0;
            $group_id = 0;
            foreach($students as $student){
	            
	            if($student == 155 || $student == 156 || $student == 157){
		            
	            }else{
	            
		            global $group_id;
		            if($i == 0){
			            $sql = "INSERT INTO assignment_group (assignment_id) VALUES ('{$assignment_id}')";
			            $this->query($sql);
			            $group_id = $this->insert_id();
		            }
		            if($i <= $num_per_group){
			            $sql = "INSERT INTO assignment_group_student (group_id, student_id) VALUES ('{$group_id}', '{$student}')";
			            $this->query($sql);
			            $i++;
		            }
		            if($i == $num_per_group){
			            $i = 0;
		            } 
		            
	            }
	        }
	        
	        /* Hard code to group Hei, Tino and Tony */
            $sql = "INSERT INTO assignment_group (assignment_id) VALUES ('{$assignment_id}')";
            $this->query($sql);
            $group_id = $this->insert_id();	  
            
            foreach([155,156,157] as $student){
	            $sql = "INSERT INTO assignment_group_student (group_id, student_id) VALUES ('{$group_id}', '{$student}')";
	            $this->query($sql);
	            $i++;                  
            }
	        
	        return true;
        }
    }


    /**
     * createAssignment function.
     *
     * @access public
     * @param mixed $title
     * @param mixed $description
     * @param mixed $dueDate
     * @param mixed $dueTime
     * @param mixed $codeType
     * @param mixed $samplecode
     * @param mixed $status
     * @param mixed $grouping
     * @param mixed $course_id
     * @return void
     */
    public function createAssignment($title, $description, $dueDate, $dueTime, $codeType, $samplecode, $status, $grouping, $course_id){
	    if(isset($title) && isset($description) && isset($dueDate) && isset($dueTime) && isset($codeType) && isset($samplecode) && isset($course_id) ){
		    $user_id = user::authService()['user_id'];
			if(user::authService()['user_type'] == 1){
				$deadline = date("Y-m-d H:i:s",strtotime($dueDate . $dueTime) );
				$sql = "INSERT INTO assignment (course_id, title, description, status, deadline, sample_code, grouping) VALUES ('{$course_id}', '{$title}', '{$description}', '{$status}', '{$deadline}', '{$samplecode}', '{$grouping}') ";
				$this->query($sql);

				$assignment_id = $this->insert_id();
				$sqlFile = "UPDATE file set type_id = '{$assignment_id}' WHERE type = 'question' AND type_id = 0 AND user_id = {$user_id} ";
				$this->query($sqlFile);

                $this->createGroup($assignment_id, $grouping);

				return $assignment_id;
			}
	    }else{
		    return false;
	    }
    }


    /**
     * editAssignment function.
     *
     * @access public
     * @param mixed $title
     * @param mixed $description
     * @param mixed $dueDate
     * @param mixed $dueTime
     * @param mixed $codeType
     * @param mixed $samplecode
     * @param mixed $status
     * @param mixed $course_id
     * @param mixed $assignment_id
     * @return void
     */
    public function editAssignment($title, $description, $dueDate, $dueTime, $codeType, $samplecode, $status, $course_id, $assignment_id){
	    if(isset($assignment_id) && isset($title) && isset($description) && isset($dueDate) && isset($dueTime) && isset($codeType) && isset($course_id) ){
		    $user_id = user::authService()['user_id'];
			if(user::isTeacher() ){
				$deadline = date("Y-m-d H:i:s",strtotime($dueDate . $dueTime) );
				$sql = "UPDATE assignment SET title = '{$title}', description = '{$description}', status = '{$status}', deadline = '{$deadline}' WHERE assignment_id = {$assignment_id}";
				$this->query($sql);
				
				//var_dump($samplecode);
				
				$fileAPI = new file();
				$fileAPI->submitSampleCode($assignment_id, $samplecode);

				$sqlFile = "UPDATE file set type_id = '{$assignment_id}' WHERE type = 'question' AND type_id = 0 AND user_id = {$user_id} ";
				$this->query($sqlFile);
				return $assignment_id;
			}
	    }else{
		    return false;
	    }
    }
    
    public function deleteAssignment($assignment_id){
	    if(isset($assignment_id) && user::isTeacher()){
		    $sql = array();
		    $sql[] = "DELETE FROM assignment WHERE assignment_id = '{$assignment_id}'";
		    $sql[] = "DELETE FROM assignment_group_student WHERE group_id IN (SELECT group_id FROM assignment_group WHERE assignment_id = '{$assignment_id}')";
		    $sql[] = "DELETE FROM assignment_group WHERE assignment_id = '{$assignment_id}'";		    
		    foreach($sql as $s){
			    $this->query($s);
			}
			return true;
	    }
    }
    
    public function addEditor($group_id){
	    $sql = "INSERT INTO assignment_group_code (group_id) VALUES ('{$group_id}') ";
	    $this->query($sql);
	    echo 1;
    }
    
    public function getEditor($group_id){
	    $sql = "SELECT editor, code FROM assignment_group_code WHERE group_id = '{$group_id}' ";
	    $result = $this->query($sql);
	    $editors = array();
	    while($row = $result->fetch_assoc()){
		    $editors[] = $row;
	    }
	    return $editors;
    }


	/**
	 * assignmentInfo function.
	 *
	 * @access public
	 * @param mixed $assignment_id
	 * @return void
	 */
	public function assignmentInfo($assignment_id){
		$user_id = user::authService()['user_id'];
		if(!isset($assignment_id) && !isset($user_id)){
			return -1;
		}

        $sql = "SELECT * FROM assignment WHERE assignment_id = '$assignment_id' ";
        $result = $this->query($sql);
        $row = $result->fetch_assoc();

        $sqlFile = "SELECT * FROM file WHERE type_id = $assignment_id AND type = 'question' ";
        $resultFile = $this->query($sqlFile);
        $files = array();
        while($rowFile = $resultFile->fetch_assoc()){
                $filepath = file::getFilename($rowFile['file_id'], $rowFile['extension']);
                $rowFile['filepath'] = $filepath;
                $files[] = $rowFile;
        }
        $row['files'] = $files;

        if(user::isStudent() ){
	        $row['group_id'] = assignment::groupID($assignment_id);
	        $row['editor'] = assignment::getEditor($row['group_id']);
	        
        }elseif(user::isTeacher() ){
			$sql2 = "SELECT COUNT(*) as group_count FROM assignment_group WHERE assignment_id = '$assignment_id' ";
			$result2 = $this->query($sql2);
			$row2 = $result2->fetch_assoc();
			$row['group_count'] = $row2['group_count'];
			$deadline = explode(" ", $row['deadline']);
			$row['due_date'] = $deadline[0];
			$row['due_time'] = $deadline[1];
			
			$sql3 = "SELECT * FROM assignment_sample_code WHERE assignment_id = '$assignment_id' ";
			$result3 = $this->query($sql3);
			$sample_codes = array();
			while($row3 = $result3->fetch_assoc()){
				$sample_codes[] = $row3;
			}
			$row['sample_code'] = $sample_codes;
		}

        return $row;
	}

}