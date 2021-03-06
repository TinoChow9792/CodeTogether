<?php
require_once "mysql.php";
require_once "user.php";
/**
 * data class.
 *
 * @extends mysql
 */
class data extends mysql{

	public $basePath;
	public $info;
	//private $course_id;
	private $group_id;

    /**
     * __construct function.
     *
     * @access public
     * @param mixed $course_id
     * @return void
     */
    public function __construct($group_id = null) {
        parent::__construct();
        $this->basePath = dirname(__FILE__);
        //Individual Course
        if(!is_null($group_id)){
	        //$this->info = $this->assignmentInfo($assignment_id);
	        $this->group_id = $group_id;
        }
    }
	
	public function uploadCollaborationData($group_id, $text, $voice, $drawing, $code){
		if(isset($group_id)){
			$user_id = user::authService()['user_id'];
			$sql = "INSERT INTO collaboration_data (group_id, user_id, text_msg_no, voice_msg_no, drawing_no, code_no) VALUES ('{$group_id}', '{$user_id}', '{$text}', '{$voice}', '{$drawing}', '{$code}')";
			$this->query($sql);
		}
	}
	
	public function getCollaborationData($group_id) {
		if(isset($group_id)){
			$sql = "SELECT U.name as username, SUM(C.code_no) as code_no, SUM(C.text_msg_no) as text_msg_no, SUM(C.voice_msg_no) as voice_msg_no, SUM(C.drawing_no) as drawing_no FROM collaboration_data C JOIN user U ON C.user_id = U.user_id WHERE C.group_id = '{$group_id}' GROUP BY C.user_id";
			$result = $this->query($sql);
			$data = array();
			if ($result->num_rows != 0){
				while($row = $result->fetch_assoc()){
					$data[] = $row;
				}
			}
			return json_encode($data);
		}
	}
}