<?php

/**
 * @file
 * This file contains functions relating to editing the course info, like rotation schedule
 * and syllabus
 */



/**
 * This form lets the user edit the course's syllabus or rotation schedule
 */
function course_search_edit_course_details_form() {
  $form = array();

  $m = 0;
  
  fp_add_css(fp_get_module_path("course_search") . "/css/course_search_style.css");
  
  $course_id = $_REQUEST["course_id"];
  $subject_id = $_REQUEST["subject_id"];
  $course_num = $_REQUEST["course_num"];

  $catalog_year = variable_get("current_catalog_year", 2006);

  $course = new Course($course_id,false,null,false,$catalog_year);
  
  $course->load_descriptive_data(false, true, false, true);

  fp_set_title(t("Edit Course Info for @course", array("@course" => "$subject_id $course_num")));

  $form["#attributes"] = array("enctype" => "multipart/form-data");
  
  $form["course_id"] = array(
    "type" => "hidden",
    "value" => $course_id,
  );

  $form["subject_id"] = array(
    "type" => "hidden",
    "value" => $subject_id,
  );

  $form["course_num"] = array(
    "type" => "hidden",
    "value" => $course_num,
  );

  $course->catalog_year = $catalog_year;
  // Find out filename/link of current syllabus.
  $filelink = "- None currently entered -";
  $filename = "";
  if ($syllabus_details = course_search_get_course_syllabus_details("", "", $course_id)) {
    $filename = $syllabus_details["filename"];
  }

  $bool_delete_cb = FALSE;
  
  if ($filename != "") {
    $filelink = $filename;
    $bool_delete_cb = TRUE;
          
  }

  $form["mark" . $m++] = array(
    "value" => "<strong>$course->title (" . $course->get_catalog_hours() . " " . t("hrs.") . ")</strong>
                <div>$course->description</div>
                ",
  );

  $form["syllabus"] = array(
    "type" => "file",
    "label" => t("Submit new sample course syllabus:"),
    "prefix" => "<hr>" . t("Current sample syllabus:") . " " . $filelink,
    "description" => t("Note: selecting a new file will replace any existing
                        syllabus.  Remember to click Submit after selecting a file."),
  );

  if ($bool_delete_cb) {
    $form["del_syllabus"] = array(
      "type" => "checkboxes",
      "options" => array("yes" => t("Delete the existing syllabus?")),
    );
  }

  if ($filename != "") {   
    $s_url = $syllabus_details["url"];
    
    $form["mark" . $m++] = array(
      "value" => "<p>" . t("Syllabus may be downloaded using this address: ") . "<a href='$s_url'>$s_url</a></p>",
    );
    
  }

  $form["mark" . $m++] = array(
    "value" => "<b>" . t("Course Rotation Schedule:") . "</b>
                <br>" . t("Select semesters/terms in which this Course
                      will be offered for the next five years."),
  );
  
  //$term_array = get_term_id_suffixes();
  $term_array = csv_to_array(variable_get("course_search_avail_term_id_suffix_order"));

  $form["mark" . $m++] = array(
    "value" => "<div class='rotation-form-checkboxes'>",
  );
  
  $term_structures = get_term_structures();

  $y = 1;
  for ($t = $catalog_year; $t <= $catalog_year + 5; $t++) {
      
    $form["year$y"] = array(
      "type" => "hidden",
      "value" => $t,
    );
    
    foreach($term_array as $x) {
      
      $check_year = $t;
       
      $term_id = $check_year . $x;
      
      // Should we make any adjustments based on the term_structure?
      // Does the term suffix ($x) call for the year to be
      // subtracted by 1?  This is the case at ULM for fall.
      // Ex:  201340  is Fall of *2012*, not 2013.
      // We can tell this because the term structure (from admin settings)      
      if (@strtoupper($term_structures[$x]["disp_adjust"]) == "[Y-1]") {        
        $term_id = ($check_year + 1) . $x;
      }
      
      
      
      $new_course = new Course();
      $new_course->term_id = $term_id;
      $cb_disp_name = $new_course->get_term_description(TRUE);
      
      $cbname = $new_course->term_id;
      
      
      $cb_default_val = array();
      // See if this box should be checked...
      $res = db_query("select * from course_rotation_schedule
                where `course_id`='?'
                and `term_id`='$term_id' ", $course_id);
      if (db_num_rows($res) > 0) {
        $cb_default_val = array("yes" => "yes");
      }
            
      $form["cb_$cbname"] = array(
        "type" => "checkboxes",
        "options" => array("yes" => $cb_disp_name),        
        "value" => $cb_default_val,
      );
      
      
    }

    $form["mark" . $m++] = array(
      "value" => "<br>",
    );

    $y++;
  }

  $form["mark" . $m++] = array(
    "value" => "</div>",
  );


  // Not anticipated check.
  $default_val = array();
  $res = db_query("SELECT * FROM course_rotation_schedule
            WHERE `course_id`='?'
            AND `term_id`='NOTA' ", $course_id);
  //debug_c_t("$t$x");
  if (db_num_rows($res) > 0)
  {
    $default_val = array("yes" => "yes");
  }

  $form["not_anticipated"] = array(
    "type" => "checkboxes",
    "label" => t("Not Anticipated:"),
    "options" => array("yes" => t("This course is not anticipated.")),
    "value" => $default_val,
    "description" => t("Checking this box means that the course is not
                        anticipated to be offered within the time range above.
                        This will override any selections made above."),
  );



  $form["submit"] = array(
    "type" => "submit",
    "value" => t("Submit"),
    "prefix" => "<hr>",
  );
   
  
  return $form;
}


function course_search_edit_course_details_form_submit($form, $form_state) {
  global $user;
  
  $faculty_id = $user->cwid;
  
  $course_id = $form_state["values"]["course_id"];
  $subject_id = $form_state["values"]["subject_id"];
  $course_num = $form_state["values"]["course_num"];
  
  //$term_array = get_term_id_suffixes();
  $term_array = csv_to_array(variable_get("course_search_avail_term_id_suffix_order"));

      
  for ($t = 1; $t <= 6; $t++) {
    $year = $form_state["values"]["year$t"];
    
    // delete the existing entries for this year & course here.
    db_query("DELETE FROM course_rotation_schedule
            WHERE `course_id`= ?
            AND `term_id` LIKE ? ", $course_id, "$year%");
            
    /////////////////////////
    // Now, for x = 1 to 5,
    // if ($_request["cb_$year$x"] == "yes")
    // then insert into table... blah blah...
    foreach($term_array as $x) {
      if ($form_state["values"]["cb_$year$x"]["yes"] == "yes") {
        db_query("INSERT INTO course_rotation_schedule
                (faculty_id, course_id, term_id, entry_value, posted)
                VALUES (?, ?, ?, ?, ?) ", $faculty_id, $course_id, "$year$x", "$subject_id~$course_num", time());                
      }
    }
            
  }      
   
   
  // Not anticipated check?
  
  // delete first...
  db_query("DELETE FROM course_rotation_schedule
            WHERE course_id = ?
            AND term_id = 'NOTA' ", $course_id);

  if ($form_state["values"]["not_anticipated"]["yes"] == "yes") {
    db_query("INSERT INTO course_rotation_schedule              
            (faculty_id, course_id, term_id, entry_value, posted)
            VALUES (?, ?, ?, ?, ?) ", $faculty_id, $course_id, "NOTA", "$subject_id~$course_num", time());

  }

  // Should we delete the current syllabus?
  if ($form_state["values"]["del_syllabus"]["yes"] == "yes") {
    if ($details = course_search_get_course_syllabus_details("", "", $course_id)) {
      $filename = $details["filename"];
      if ($filename != "") {
        unlink("./custom/files/syllabi/$filename");
        db_query("DELETE FROM course_syllabi
                WHERE `course_id`= ? ", $course_id);
        
      }
    }
  }

  // Did the user try to upload a file?
  if (trim($_FILES["syllabus"]["tmp_name"]) != "")
  {
    $test = course_search_upload_attachment($course_id, $subject_id, $course_num);
    if (!$test)
    {
      fp_add_message(t("There was an error uploading attachment.  It is possible this is a write-permission
                        issue with the server."));
    } else {
      // success.
    }
  }      
   
  fp_add_message(t("This course's details have been updated successfully."));
  // TODO:  Do a "goto" back to wherever we came from
}




function course_search_upload_attachment($course_id, $subject_id, $course_num) {

  // If any files were attached, go ahead and upload them.
  $datetime = date ("_y-j-m_g-i-sa", time());


  $tmp_filename = $_FILES["syllabus"]["tmp_name"];
  if (trim($tmp_filename) != "") {
    // Meaning, yes, a file was uploaded.
    $suffix = $datetime;
    // Create the new filename, based on the id's its been assigned.
    $original_filename = $_FILES["syllabus"]["name"];
    // replace problem characters...
    $original_filename = str_replace("'", "", $original_filename);
    $original_filename = str_replace('"', '', $original_filename);
    $original_filename = str_replace(",", "", $original_filename);

    $temp = explode("\.", $original_filename);
    $ext = $temp[count($temp) - 1];  // get the original extension.
    $new_filename = $original_filename . ".$suffix.$ext";


    // Does the directory/files/syllabi exist?
    // If not, we will create it.
    $files_path = $GLOBALS["fp_system_settings"]["file_system_path"];
     
    if (!is_dir("$files_path/custom/files/syllabi")) {
      if (!mkdir("$files_path/custom/files/syllabi")) {
        return FALSE;
      }
    }    

    // Try to copy...
    if (!copy($tmp_filename, "$files_path/custom/files/syllabi/$new_filename")) {      
      return FALSE;
    }

    // If we are here, then there is success!  We should update the
    // table with the new filename, which is $new_filename.
    // delete the existing entry, if any.

    // We need to unlink any existing syllabus file...
    if ($file_details = course_search_get_course_syllabus_details($subject_id, $course_num, $course_id)) {
      $filename = $file_details["filename"];    
      if (!unlink("$files_path/custom/files/syllabi/$filename")) {
        fp_add_message(t("Unable to delete existing file:") . " $filename");
      }
    }

    db_query("DELETE FROM course_syllabi
              WHERE course_id = '?' ", $course_id);

    // Okay, now add the new entry.
    db_query("INSERT INTO course_syllabi
            (course_id, course_perm_id, filename, posted)
            values (?, ?, ?, ? )", $course_id, $subject_id . '_' . $course_num, $new_filename, time());



  }

  return TRUE;

}






/**
 * Display a plain list of courses, making it easier for admins to edit schedules and details in one spot.
 *
 */
function course_search_display_edit_courses()
{
	
  $rtn = "";
	
  $current_catalog_year = variable_get("current_catalog_year", "");
  // catalog_year is always just whatever the current year is.
  $catalog_year = $current_catalog_year;
  
	
	$cc = 1;

	// Get the "upper range" (UR) and
	// lower range (LR) of the courses in question...
	$ur = trim($_GET["ur"]);
	$lr = trim($_GET["lr"]);
	if ($ur == "")
	{ // meaning, no range was set.  Use A - AZZZZZ, so, all of the A's.
		$ur = $_SESSION["dec_ur"];
		$lr = $_SESSION["dec_lr"];
		if ($ur == "")
		{ // if still blank, assign it..
			$ur = "A";
			$lr = "AZZZZ";
		}

	}
	$_SESSION["dec_ur"] = $ur;
	$_SESSION["dec_lr"] = $lr;


	$rtn .= "
			<div class='tenpt' style='margin-bottom: 20px;'>
				" . t("To update a course's sample syllabus or schedule,
				please select the course from the list below.") . "
				<br>				
			  " . l(t("View Reports"), "tools/course-search/view-reports") . "
			</div>
			";

	
	
  $letter_ranges = array(
    "A" => array("A", "AZZZZ"),
    "B" => array("B", "BZZZZ"),
    "C-CN" => array("C", "CNZZZ"),
    "CO-CZ" => array("CO", "CZZZZ"),
    "D" => array("D", "DZZZZ"),
    "E" => array("E", "EZZZZ"),
    "F" => array("F", "FZZZZ"),
    "G" => array("G", "GZZZZ"),
    "H" => array("H", "HZZZZ"),
    "I-L" => array("I", "LZZZZ"),
    "M-MR" => array("M", "MRZZZ"),
    "MS-MZ" => array("MS", "MZZZZ"),
    "N-O" => array("N", "OZZZZ"),
    "P" => array("P", "PZZZZ"),
    "Q-R" => array("Q", "RZZZZ"),
    "S-SO" => array("S", "SOZZZ"),
    "SP-SZ" => array("SP", "SZZZZ"),
    "T-Z" => array("T", "ZZZZZ"),
  );


  $rtn .= "<div class='admin-courses-select-letter-bar'>
          ";
  foreach($letter_ranges as $disp => $vals) {
    $rtn .= l($disp, "tools/course-search/edit-list", "ur=" . $vals[0] . "&lr=" . $vals[1], array("class" => "admin-courses-letter-link")) . " &nbsp; ";
  }
	
  $rtn .= "</div>
            <hr>";
	
	



	$rtn .= "<table border='0' cellpadding='3' cellspacing='0'>
			<tr>
				<td class='tenpt' width='95%'>Course</td>
				<td class='tenpt'>Sch</td>
				<td class='tenpt'>Syl</td>
				<td class='tenpt'>Updated</td>
			</tr>";

	$res = db_query("SELECT * FROM courses
                        WHERE 
                        `catalog_year`='?' AND
                        `subject_id` BETWEEN '?' AND '?'
                        AND `delete_flag`='0'
                        AND `exclude`='0'
                        ORDER BY `subject_id`, `course_num` ", $catalog_year, $ur, $lr);
	while ($cur = db_fetch_array($res)) {
		extract($cur, 3, "db");

		$syl = $sch = $updated = "";
		// Right here, maybe check to see if this course has
		// a syllabus and/or schedule entered?
		
		$syllabus_details = course_search_get_course_syllabus_details("", "", $db_course_id);
		$filename = $syllabus_details["filename"];
		
		
		if ($filename != "") {
      		  
      $url = $syllabus_details["url"];			  
      
			$syl = "<a href='$url'><img src='" . fp_theme_location() . "/images/document_icon.gif' border='0'></a>";
			
			$updated = $syllabus_details["posted"];
		}
		$schedule_array = course_search_get_course_rotation_schedule($db_course_id, $catalog_year, 100, TRUE);
		$not_ant = course_search_get_course_rotation_schedule_not_anticipated($db_course_id);
		if (count($schedule_array) > 0 || $not_ant == true) {
			$sch = "<img src='" . fp_theme_location() . "/images/small_check.gif'>";
			if ($not_ant) {
				$sch = "NA";
			}
			// When was the schedule last updated?
			$res2 = db_query("SELECT * FROM course_rotation_schedule
							WHERE `course_id`='?' limit 1", $db_course_id);
			$cur2 = db_fetch_array($res2);
			$updated2 = $cur2["posted"];
			if ($updated2 > $updated) {
				$updated = $updated2;
			}
		}

		if ($updated != "") {
			$updated = date("n/d/y",$updated);
		}


		
		$hrs = $db_min_hours;
		if (trim($db_min_hours) != trim($db_max_hours)) {
			$hrs .= " - $db_max_hours";
		}

		$hrs .= " hrs.";

		$rep_hours = "";
		if ($db_repeat_hours > $db_min_hours)
		{
			$rep_hours = " rep to $db_repeat_hours hrs.";
		}

		// remove special chars from subject_id...
		$display_subject_id = $db_subject_id;
		$db_subject_id = urlencode($db_subject_id);


		$rtn .= "<tr style='$spanstyle'>
				<td valign='top' width='90%'>";
		
		/*$rtn .= "<div style='padding:3px;'>
					<a href='$moduleActionURL&performAction=editSpecificCourse&courseID=$db_course_id&subjectID=$db_subject_id&courseNum=$db_course_num'>$displaySubjectID $db_course_num - $db_title</a><!-- - $hrs$repHours --></div>";
*/
		
		$rtn .= l("$display_subject_id $db_course_num - $db_title", "tools/course-search/edit-info-details", "course_id=$db_course_id&subject_id=$db_subject_id&course_num=$db_course_num");
		// https://orion.ulm.edu/flightpath/tools/course-search/edit-info-details?course_id=468798&subject_id=ACCT&course_num=1010
		
		$rtn .= "</td>
				<td align='center'>$sch</td>
				<td align='center'>$syl</td>
				<td align='center' style='font-size: 8pt;'>$updated</td>
					</tr>";

	} // while


	$rtn .= "</table>";


	return $rtn;


}



function course_search_display_view_reports() {
  
  $rtn = "";
  
  $rtn .= "<h2 class='title'>" . t("View Reports") . "</h2>";
  $rtn .= "<ul>";

  $rtn .= "<li>" . l(t("[CSV] Anticipated Offerings - All Courses"), "tools/course-search/get-offering-csv") . "</a>
              - " . t("This report shows all courses in the catalog's anticipated offerings
              for the next 5 years.  Will be downloaded as a CSV file.
              <b>May take up to a minute to load.</b>") . "</li> ";
  
  $rtn .= "</ul>";
  return $rtn;
}



function course_search_get_offering_csv() {
  $csv = "";
  $csv .= "SUBJ, NUM, COL, DEPT, SYL UPDATED, ";
  
  $catalog_year = variable_get("current_catalog_year", "");
  
  $course_term_array = array();
  $check_term_array = array();
    
  $term_array = csv_to_array(variable_get("course_search_avail_term_id_suffix_order"));  
  
	for ($t = $catalog_year; $t <= $catalog_year + 5; $t++)
	{
		foreach($term_array as $x)
		{
		  $check_year = $t;
		  		  
			$term_id = $check_year . $x;
			
			$check_term_array[] = $term_id;
			
      $new_course = new Course();
      $new_course->term_id = $term_id;
      $disp_name = $new_course->get_term_description(TRUE);
			
			$csv .= " " . $disp_name . " ($term_id) ,";
			
			// While we are here, we are going to get an array of ALL the courses
			// which have an entry for this term in our table.  This should make
			// it faster to look up later.
			$res = db_query("SELECT * FROM course_rotation_schedule
													WHERE `term_id`='?' ", $term_id);
			while ($cur = db_fetch_array($res)) {
			  $course_term_array[$term_id][$cur["course_id"]] = TRUE;
			}
			
		}
	}
  
	// Take off last comma of csv.
	$csv = substr($csv, 0, -1);	

	//var_dump($courseTermArray);
	
	//debugCT($csv);
		
	$csv .= "\n";
	//return;
   
	$result = db_query("SELECT * FROM courses
                        WHERE 
                        `catalog_year`='?'                         
                        AND `delete_flag`='0'
                        AND `exclude`='0'
                        ORDER BY `subject_id`, `course_num` ", $catalog_year);
	while ($cur = db_fetch_array($result)) {
	  extract($cur, 3, "db");
	  //debugCT($db_subject_id . $db_course_num);
	  
	  $csv .= "$db_subject_id, $db_course_num, ";
	  
	  
	  // What college does this course belong to?
    $res2 = db_query("SELECT * FROM subjects
                          WHERE subject_id = '?' ", $db_subject_id);
    $cur2 = db_fetch_array($res2);
    $college = $cur2["college"];
    $dept_name = $cur2["title"];
    	  
	  $csv .= "$college, $dept_name, ";
    	  
	  // Also, when was their last syllabus submitted?
	  $syl = "";
	  $res2 = db_query("SELECT * FROM course_syllabi WHERE course_id = '?'", $db_course_id);
	  $cur2 = db_fetch_array($res2);
	  $syl = format_date($cur2["posted"]);
	  
	  $csv .= "$syl, ";
	  
	  
	  // Now, get course offerings for the next X terms...
    foreach ($check_term_array as $term_id) {
			// See if this term is offered for this course
			if ($course_term_array[$term_id][$db_course_id] == TRUE) {
				$csv .= " X,";
			}
			else {
			  $csv .= ",";
			}     
    }
	  
	  // Take off last comma of csv.
    $csv = substr($csv, 0, -1);	
    $csv .= "\n";
    
	}  
  
  header('Content-type: text/csv');
  header('Content-Disposition: attachment; filename="report_ant_offering.csv"');
  print $csv;  
  die;
}