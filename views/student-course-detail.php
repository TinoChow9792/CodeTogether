<?php
$courseAPI = new course($course_id);
$course = $courseAPI->info();
?>

<!-- START PAGE CONTENT WRAPPER -->
<div class="page-content-wrapper">
    <!-- START PAGE CONTENT -->
    <div class="content">
        <!-- START JUMBOTRON -->
        <div class="jumbotron" data-pages="parallax">
            <div class="container-fluid container-fixed-lg sm-p-l-20 sm-p-r-20">
                <div class="inner">
                    <!-- START BREADCRUMB -->
                    <ul class="breadcrumb">
                        <li>
                            <a href="<?= $router->generate('course') ?>" class="">Course</a>
                        </li>
                        <li><a href="#" class="active"><?= $course['name'] ?></a>
                        </li>
                    </ul>
                    <!-- END BREADCRUMB -->
                </div>
            </div>
        </div>
        <!-- END JUMBOTRON -->  
		<!-- START CONTAINER FLUID -->
		<div class="container-fluid container-fixed-lg">
		<!-- BEGIN PlACE PAGE CONTENT HERE -->
		
		
			<div class="row">				
				<div class="col-md-12">
					<h1><?= $course['course_code'] ?> <?= $course['name'] ?></h1>				
				</div>						
			</div>
			
			<div class="row">									
				<div class="col-md-8">					
					<?php
					foreach($courseAPI->courseAssignmentList() as $assignment ):	
					?>
					<div class="row">
						<a href="<?= $router->generate('assignment', array('assignment_id' => $assignment['assignment_id']) ) ?>" style="color: inherit">
							<div class="col-md-12">							                
								<div class="panel panel-default">
									<div class="panel-heading separator">
										<div class="panel-title">
											<?= $assignment['title'] ?>
										</div>
									</div>
									<div class="panel-body m-t-10">									
										<p><?= $assignment['description'] ?></p>
									</div>
								</div>                		
							</div>
						</a>					
					</div>
					<?php
					endforeach;	
					?>
					
				</div>
				<div class="col-md-4">											                
					<div class="panel panel-default">
						<div class="panel-heading separator">
							<div class="panel-title">
								<?= sizeof($course['studentList']) ?> students enrolled
							</div>
						</div>
						<div class="panel-body m-t-10 p-b-10" style="max-height: 300px; overflow: scroll">							
	                        <?php
	                        foreach($course['studentList'] as $student):
	                        ?>															
							<div>
								<div class="profile-img-wrapper m-t-5 inline">
									<img width="35" height="35" alt="" src="<?= user::avatar($student['user_id']) ?>">
									<div class="chat-status available">
									</div>
								</div>
								<div class="inline m-l-10">
									<p class="small hint-text m-t-5"><?= $student['name'] ?>
										<br>from <?= $student['school_name'] ?>
									</p>
								</div>
							</div>	
							<?php
							endforeach;	
							?>																								
						</div>
					</div>                							
				</div>
			</div>
				
				

			
		
		
		<!-- END PLACE PAGE CONTENT HERE -->
		</div>
		<!-- END CONTAINER FLUID -->
    </div>
   </div>
    <!-- END PAGE CONTENT -->