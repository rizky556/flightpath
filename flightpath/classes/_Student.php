<?php
/*
FlightPath was originally designed and programmed by the 
University of Louisiana at Monroe. The original source is 
copyright (C) 2011-present by the University of Louisiana at Monroe.

FlightPath is considered "open source" under the 
GNU General Public License, version 3 or any later version. 
This covers any related files and documentation packaged with 
FlightPath. 

The license is defined in full here: http://www.gnu.org/licenses/gpl.html,
and reproduced in the LICENSE.txt file.

You may modify FlightPath's source code, but this copyright and license
notice must not be modified, and must be included with the source code.
------------------------------
*/

class _Student
{
	public $student_id, $name, $major_code, $gpa, $cumulative_hours, $catalog_year;
	public $list_courses_taken, $list_courses_advised, $list_courses_added, $db, $rank;
	public $list_standardized_tests, $list_substitutions;
	public $list_transfer_eqvs_unassigned;
	public $array_settings, $array_significant_courses, $array_hide_grades_terms;
	
	/*
	* $student_id		The student's (database-generated) Campus Wide ID.
	* $name				The student's name by which they'll be referred to on screen.
	* $major_code		ACCT, CSCI, etc.
	* $gpa				Grade point average. Ex: 3.12, 2.97, etc.
	* $cumulative_hours	How many hours the student has earned to date.
	* $catalog_year		What catalog are the listed as? Ex: 2007, 2008, etc.
	* $list_courses_taken This is a list of all the courses the student has taken.
	It is made up of Course objects.
	*/


	function __construct($student_id = "", DatabaseHandler $db = NULL)
	{
		$this->student_id = $student_id;
		$this->array_hide_grades_terms = array();
		$this->array_significant_courses = array();  // array of course_ids
		// the student has taken, or has subs for (or transfer eqv's).
		// used later to help speed up assignCoursesToList in FlightPath.

		$this->db = $db;
		if ($db == NULL)
		{
			$this->db = get_global_database_handler();
		}
		// If a cwid was specified, then go ahead and load and assemble
		// all information in the database on this student.
		if ($student_id != "")
		{
		  $this->determine_terms_to_hide_grades();
			$this->load_student();
		}

	}

	
	/**
	 * This is a stub function.  If you are planning on hiding course grades
	 * for a term at a time, you should override this method in /custom/classes
	 * and place that logic here.
	 * 
	 * For example,
	 * at ULM, students cannot see their final grades for a term until they
	 * have completed their course evaluations for every course they took that
	 * term, OR, until 2 weeks have passed.  
	 * 
	 * 
	 *
	 */
  function determine_terms_to_hide_grades()
	{
	  return;
	}		
	
	
	function load_student()
	{

		$this->list_transfer_eqvs_unassigned = new CourseList();
		$this->list_courses_taken = new CourseList();
		$this->list_courses_added = new CourseList();

		$this->list_substitutions = new SubstitutionList();

		$this->list_standardized_tests = new ObjList();
		$this->array_settings = array();

		if ($this->student_id != "")
		{
			$this->load_transfer_eqvs_unassigned();
			$this->load_courses_taken();
			$this->load_student_data();
			$this->load_test_scores();
			$this->load_settings();
			$this->load_significant_courses();
			//$this->load_unassignments();
			//$this->load_student_substitutions();
		}
	}

	function load_significant_courses()
	{
		// This will attempt to add as much to the array_significant_courses
		// as we can, which was not previously determined.
		// For example: courses we were advised to take and/or
		// substitutions.

		// Now, when this gets called, it's actually *before* we
		// write any subs or advisings to the database, so what we
		// actually need to do is go through the POST
		// and add any course_id's we find.
		// In the future, perhaps it would be more efficient
		// to have just one POST variable to look at, perhaps
		// comma-seperated.
		

		// Look in the database of advised courses for ANY course advised in
		// the range of advisingTermIDs.
		$advising_term_ids = $GLOBALS["setting_available_advising_term_ids"];
		$temp = split(",",$advising_term_ids);
		foreach ($temp as $term_id)
		{
			$term_id = trim($term_id);
			//admin_debug($term_id);
			$res = $this->db->db_query("SELECT * FROM advising_sessions a,
							advised_courses b
							WHERE a.student_id='?'
							AND a.advising_session_id = b.advising_session_id
							AND a.term_id = '?' 
							AND a.is_draft = '0' ", $this->student_id, $term_id);
			while ($cur = $this->db->db_fetch_array($res))
			{
				$this->array_significant_courses[$cur["course_id"]] = true;
			}
		}


		// Now, look for any course which a substitution might have been
		// performed for...
		$res = $this->db->db_query("SELECT * FROM student_substitutions
										WHERE student_id='?' ", $this->student_id);
		while ($cur = $this->db->db_fetch_array($res))
		{
			$this->array_significant_courses[$cur["required_course_id"]] = true;
		}


	}

	function load_significant_courses_from_list_courses_taken()
	{
		// Build the array_significant_courses
		// entriely from list_courses_taken.
		$this->list_courses_taken->reset_counter();
		while($this->list_courses_taken->has_more())
		{
			$c = $this->list_courses_taken->get_next();
			$this->array_significant_courses[$c->course_id] = true;
		}
	}
	
	
	function load_settings()
	{
		// This will load & set up the array_settings variable for this
		// student.
		$res = $this->db->db_query("SELECT * FROM student_settings
									WHERE 
									student_id='?' ", $this->student_id);
		$cur = $this->db->db_fetch_array($res);

		if ($arr = unserialize($cur["settings"])) {
			$this->array_settings = $arr;
		}
    
	}

	function load_transfer_eqvs_unassigned()
	{
		$res = $this->db->db_query("SELECT * FROM student_unassign_transfer_eqv
									WHERE
									student_id='?' 
									AND delete_flag='0'
									ORDER BY id ", $this->student_id);
		while($cur = $this->db->db_fetch_array($res))
		{
			extract ($cur, 3, "db");
			$new_course = new Course();
			$new_course->bool_transfer = true;
			$new_course->course_id = $db_transfer_course_id;
			$new_course->db_unassign_transfer_id = $db_id;
			//admin_debug($new_course->course_id);
			$this->list_transfer_eqvs_unassigned->add($new_course);

		}
	}


	function init_semester_courses_added()
	{
		// The "Add a Course" box on screen is really just a
		// semester, with the number -88, with a single group,
		// also numbered -88.
		$this->semester_courses_added = new Semester(-88);
		$this->semester_courses_added->title = "Courses Added by Advisor";

		// Now, we want to add the Add a Course group...
		$g = new Group();
		$g->group_id = -88;
		// Since it would take a long time during page load, we will
		// leave this empty of courses for now.  It doesn't matter anyway,
		// as we will not be checking this group for course membership
		// anyway.  We only need to load it in the popup.
		$g->hours_required = 99999;  // Nearly infinite selections may be made.
		$g->assigned_to_semester_num = -88;
		$g->title = "Add an Additional Course";

		$this->semester_courses_added->list_groups->add($g);

	}


	function load_unassignments()
	{
		// Load courses which have been unassigned from groups
		// or the bare degree plan.
		$res = $this->db->db_query("SELECT * FROM student_unassign_group
							WHERE 
								student_id='?' 
								AND delete_flag='0' ", $this->student_id);
		while($cur = $this->db->db_fetch_array($res))
		{
			extract ($cur, 3, "db");

			if ($taken_course = $this->list_courses_taken->find_specific_course($db_course_id, $db_term_id, (bool) $db_transfer_flag, true))
			{
				// Add the group_id to this courses' list of unassigned groups.
				$new_group = new Group();
				$new_group->group_id = $db_group_id;
				$new_group->db_unassign_group_id = $db_id;


				$taken_course->group_list_unassigned->add($new_group);
			}

		}



	}



	function load_test_scores()
	{
		// If the student has any scores (from standardized tests)
		// then load them here.

		$st = null;
		
		
    // Let's pull the needed variables out of our settings, so we know what
		// to query, because this is a non-FlightPath table.
		$tsettings = $GLOBALS["fp_system_settings"]["extra_tables"]["flightpath_resources:student_tests"];
		$tfa = (object) $tsettings["fields"];  //Convert to object, makes it easier to work with.  
		$table_name_a = $tsettings["table_name"];
				
		$tsettings = $GLOBALS["fp_system_settings"]["extra_tables"]["flightpath_resources:tests"];
		$tfb = (object) $tsettings["fields"];  //Convert to object, makes it easier to work with.  
		$table_name_b = $tsettings["table_name"];
		
		$res = $this->db->db_query("		          
		          SELECT * FROM $table_name_a a,$table_name_b b 
							WHERE 
								$tfa->student_id = '?' 
								AND a.$tfa->test_id = b.$tfb->test_id
								AND a.$tfa->category_id = b.$tfb->category_id
							ORDER BY $tfa->date_taken DESC, $tfb->position ", $this->student_id);		
		while($cur = $this->db->db_fetch_array($res))
		{
			
		  $c++;
		  
		  $db_position = $cur[$tfb->position];
		  $db_datetime = $cur[$tfa->date_taken];		  
		  $db_test_id = $cur[$tfb->test_id];
		  $db_test_description = $cur[$tfb->test_description];
		  $db_category_description = $cur[$tfb->category_description];
		  $db_category_id = $cur[$tfb->category_id];
		  $db_score = $cur[$tfa->score];
		  
		  
			if (!(($db_datetime . $db_test_id) == $old_row))
			{
				// We are at a new test.  Add the old test to our list.
				if ($st != null)
				{
					//admin_debug("adding " . $st->to_string());
					$this->list_standardized_tests->add($st);

				}

				$st = new StandardizedTest();
				$st->test_id = $db_test_id;
				$st->date_taken = $db_datetime;
				$st->description = $db_test_description;
				$old_row = $db_datetime . $db_test_id;

			}

			$st->categories[$db_position . $c]["description"] = $db_category_description;
			$st->categories[$db_position . $c]["category_id"] = $db_category_id;
			$st->categories[$db_position . $c]["score"] = $db_score;

			//admin_debug(count($st->categories));

		}

		// Add the last one created.
		if ($st != null)
		{
			$this->list_standardized_tests->add($st);
		}

		//print_pre($this->list_standardized_tests->to_string());

	}


	function load_student_substitutions()
	{
		// Load the substitutions which have been made for
		// this student.
		
		// Meant to be called AFTER load_courses_taken.
		$this->list_substitutions = new SubstitutionList();
		
		$res = $this->db->db_query("SELECT * FROM
						student_substitutions
						WHERE student_id='?'
						AND delete_flag='0' ", $this->student_id);
		while($cur = $this->db->db_fetch_array($res))
		{

			$sub_id = $cur["id"];
			$sub_course_id = $cur["sub_course_id"];
			$sub_term_id = $cur["sub_term_id"];
			$sub_bool_transfer = (bool) $cur["sub_transfer_flag"];
			$sub_hours = $cur["sub_hours"];
			$sub_remarks = trim($cur["sub_remarks"]);
			$faculty_id = $cur["faculty_id"];

			if (strstr($sub_term_id, "9999"))
			{
				// was an unknown semester.  Let's set it lower so
				// it doesn't screw up my sorting.
				$sub_term_id = 11111;
			}


			// Okay, look to see if we can find the course specified by this
			// courseSubstitution within the list of courses which the student
			// has taken.  If the subHours is less than the hours_awarded for the
			// particular course, it means the course has been split up!

			if($taken_course = $this->list_courses_taken->find_specific_course($sub_course_id, $sub_term_id, $sub_bool_transfer, true))
			{
				
								
				// If this takenCourse is a transfer credit, then we want to remove
				// any automatic eqv it may have set.
				// We can do this easily by setting its course_id to 0.
				if ($sub_bool_transfer == true)
				{
					$taken_course->temp_old_course_id = $taken_course->course_id;
					$taken_course->course_id = 0;
				}

				if ($sub_hours == 0)
				{ // If none specified, assume its the full amount.
					$sub_hours = $taken_course->hours_awarded;
				}


				if (($taken_course->hours_awarded > $sub_hours))
				{
					// Okay, now this means that the course which we are
					// using in the substitution-- the course which the student
					// has actually taken-- is being split up in the substitution.
					// We are only using a portion of its hours.
					$remaining_hours = $taken_course->hours_awarded - $sub_hours;
					
					// Create a clone of the course with the leftover hours, and add
					// it back into the list_courses_taken.
					$new_course_string = $taken_course->to_data_string();
					$new_course = new Course();
					$new_course->load_course_from_data_string($new_course_string);

					$new_course->bool_substitution_split = true;
					$new_course->bool_substitution_new_from_split = true;

          $new_course->subject_id = $taken_course->subject_id;
          $new_course->course_num = $taken_course->course_num;
					
					$new_course->hours_awarded = $remaining_hours;
					if (is_object($new_course->course_transfer))
					{
						$new_course->course_transfer->hours_awarded = $remaining_hours;
					}

					$taken_course->bool_substitution_split = true;
					$taken_course->hours_awarded = $sub_hours;
					if (is_object($taken_course->course_transfer))
					{
						$taken_course->course_transfer->hours_awarded = $sub_hours;
					}


					
					// Add the newCourse back into the student's list_courses_taken.
					$this->list_courses_taken->add($new_course);

				}


				$taken_course->substitution_hours = $sub_hours;
				$taken_course->bool_substitution = true;
				$taken_course->display_status = "completed";
				$taken_course->db_substitution_id = $sub_id;


				$substitution = new Substitution();

				if ($cur["required_course_id"] > 0)
				{
					$course_requirement = new Course($cur["required_course_id"]);
					
					$this->array_significant_courses[$course_requirement->course_id] = true;

				} else {
					// This is a group addition!
					$course_requirement = new Course($sub_course_id, $sub_bool_transfer);
					$this->array_significant_courses[$sub_course_id] = true;					
					$substitution->bool_group_addition = true;
				}

				$course_requirement->assigned_to_group_id = $cur["required_group_id"];
				$course_requirement->assigned_to_semester_num = $cur["required_semester_num"];
				$taken_course->assigned_to_group_id = $cur["required_group_id"];
				$taken_course->assigned_to_semester_num = $cur["required_semester_num"];

				$substitution->course_requirement = $course_requirement;

				
				$substitution->course_list_substitutions->add($taken_course);
				

				$substitution->remarks = $sub_remarks;
				$substitution->faculty_id = $faculty_id;
				$this->list_substitutions->add($substitution);



			} else {
				//admin_debug("Taken course not found. $sub_course_id $sub_term_id transfer: $sub_bool_transfer");
			}

		}

		//print_pre($this->list_courses_taken->to_string());
		//print_pre($this->list_substitutions->to_string());


	}


	/**
	 * This loads a student's personal data, like name and so forth.
	 *
	 */
	function load_student_data()
	{

	  // Let's pull the needed variables out of our settings, so we know what
		// to query, because this is a non-FlightPath table.
		$tsettings = $GLOBALS["fp_system_settings"]["extra_tables"]["human_resources:students"];
		$tf = (object) $tsettings["fields"];  //Convert to object, makes it easier to work with.  
		$table_name = $tsettings["table_name"];

		
	  // Let's perform our queries.
		$res = $this->db->db_query("SELECT * FROM $table_name 
						          WHERE $tf->student_id = '?' ", $this->student_id);
		$cur = $this->db->db_fetch_array($res);

		

		$this->cumulative_hours = $cur[$tf->cumulative_hours];
		$this->gpa = $cur[$tf->gpa];
		$this->rank = $this->get_rank_description($cur[$tf->rank_code]);
		$this->major_code = $cur[$tf->major_code];

		$this->name = ucwords(strtolower($cur[$tf->f_name] . " " . $cur[$tf->l_name]));

		$catalog = $cur[$tf->catalog_year];
		
		// If this is written in the format 2006-2007, then we just want
		// the first part.  Luckily, this will still work even if there WASN'T
		// a - in there ;)
	  $temp = explode("-", $catalog);
	  $this->catalog_year = $temp[0];

	}

	
	/**
	 * Given a rank_code like FR, SO, etc., get the english
	 * description. For example: Freshman, Sophomore, etc.
	 *	 
	 */
	function get_rank_description($rank_code = "") {
    $rank_array = array(
      "FR"=>"_freshman", 
      "SO"=>"_sophomore",
      "JR"=>"_junior", 
      "SR"=>"_senior", 
      "PR"=>"_professional"
    );	  
    
    return $rank_array[$rank_code];
        
	}
	
	

	/**
	 * Returns a student's degree plan object.
	 *
	 */
	function get_degree_plan($bool_load_full = true, $bool_ignore_settings = false)
	{
		
	  $t_major_code = $this->get_major_and_track_code($bool_ignore_settings);
		//admin_debug($t_major_code);
		$degree_id = $this->db->get_degree_id($t_major_code, $this->catalog_year);
		if ($bool_load_full)
		{
			$degree_plan = new DegreePlan($degree_id, $this->db);
		} else {
			$degree_plan = new DegreePlan();
			$degree_plan->degree_id = $degree_id;
			$degree_plan->load_descriptive_data();
		}

		return $degree_plan;
	}


	/**
	 * Enter description here...
	 * Returns the major code and trackCode, if it exists in this form:
	 *  MAJOR|CONC_TRACK
	 *  Though usually it will be:
	 * MAJR|_TRCK
	 * Asumes you have already called "load_settings()";
	 */
	function get_major_and_track_code($bool_ignore_settings = false)
	{

		$rtn = "";
		$major_code = "";

		if ($this->array_settings["major_code"] != "")
		{ // If they have settings saved, use those...
			if ($this->array_settings["track_code"] != "")
			{
				// if it does NOT have a | in it already....
				if (!strstr($this->array_settings["major_code"], "|"))
				{
					$rtn = $this->array_settings["major_code"] . "|_" . $this->array_settings["track_code"];
				} else {
					// it DOES have a | already, so we join with just a _.  This would
					// be the case if we have a track AND an concentration.
					$rtn = $this->array_settings["major_code"] . "_" . $this->array_settings["track_code"];
				}
			} else {
				$rtn = $this->array_settings["major_code"];
			}
			$major_code = $this->array_settings["major_code"];
		} else {
			$rtn = $this->major_code;
		}

		//admin_debug($this->array_settings["major_code"]);
		
		if ($bool_ignore_settings == true)
		{
			$rtn = $this->major_code;
		}


		return $rtn;

	}

	
	
	function load_courses_taken($bool_load_transfer_credits = true)
	{

	  $retake_grades = csv_to_array($GLOBALS["fp_system_settings"]["retake_grades"]);
	  // Let's pull the needed variables out of our settings, so we know what
		// to query, because this involves non-FlightPath tables.
		$tsettings = $GLOBALS["fp_system_settings"]["extra_tables"]["course_resources:student_courses"];
		$tf = (object) $tsettings["fields"];  //Convert to object, makes it easier to work with.  
		$table_name = $tsettings["table_name"];

		// This will create and load the list_courses_taken list.
		// contains SQL queries to fully create the list_courses_taken.
		$res = $this->db->db_query("SELECT *	FROM $table_name									
                							 WHERE 
                								$tf->student_id = '?' ", $this->student_id);
	
		while($cur = $this->db->db_fetch_array($res))
		{

			// Create a course object for this course...
			$is_transfer = false;
			$course_id = $this->db->get_course_id($cur[$tf->subject_id], $cur[$tf->course_num]);

			if (!$course_id)
			{
				admin_debug("Course not found while trying to load student data: {$cur[$tf->subject_id]} {$cur[$tf->course_num]}");
				continue;
			}

			$new_course = new Course();
			$new_course->course_id = $course_id;

			// Load descriptive data for this course from the catalog (so we can get min, max, and repeat hours)
			$new_course->load_descriptive_data();

			// Now, over-write whatever we got from the descriptive data with what the course was called
			// when the student took it.
			$new_course->subject_id = $cur[$tf->subject_id];
			$new_course->course_num = $cur[$tf->course_num];
			$new_course->grade = $cur[$tf->grade];
			$new_course->term_id = $cur[$tf->term_id];
			
			// Is this grade supposed to be hidden from students?
			if (in_array($new_course->term_id, $this->array_hide_grades_terms)
			  && $_SESSION["fp_user_type"] == "student")
			{
			  $new_course->bool_hide_grade = true;
			}			
			
			$new_course->hours_awarded = trim($cur[$tf->hours_awarded]);
			$new_course->display_status = "completed";
			$new_course->bool_taken = true;
			
			// Was this course worth 0 hours but they didn't fail it?
			// If so, we need to set it to actually be 1 hour, and
			// indicate this is a "ghost hour."
			if (!in_array($new_course->grade, $retake_grades) 
			     && $new_course->hours_awarded == 0) 			
			{
			  $new_course->hours_awarded = 1;
			  $new_course->bool_ghost_hour = TRUE;
			}			
			
			// Now, add the course to the list_courses_taken...
			$this->list_courses_taken->add($new_course);
			$this->array_significant_courses[$course_id] = true;
			
		}


		
		if ($bool_load_transfer_credits == false) {
			return;
		}
		
		
		// Tranfer credits?  Get those too...
	  // Let's pull the needed variables out of our settings, so we know what
		// to query, because this involves non-FlightPath tables.
		$tsettings = $GLOBALS["fp_system_settings"]["extra_tables"]["course_resources:student_transfer_courses"];
		$tfa = (object) $tsettings["fields"];  //Convert to object, makes it easier to work with.  
		$table_name_a = $tsettings["table_name"];
			
		$tsettings = $GLOBALS["fp_system_settings"]["extra_tables"]["course_resources:transfer_courses"];
		$tfb = (object) $tsettings["fields"];  //Convert to object, makes it easier to work with.  
		$table_name_b = $tsettings["table_name"];
		
		
		$res = $this->db->db_query("
                  			SELECT *
                  			FROM $table_name_a a, 
                  			     $table_name_b b 
                  			WHERE a.$tfa->transfer_course_id = b.$tfb->transfer_course_id
                  			AND a.$tfa->student_id = '?' ", $this->student_id);

		while($cur = $this->db->db_fetch_array($res))
		{
			$transfer_course_id = $cur[$tfa->transfer_course_id];
			$institution_id = $cur[$tfb->institution_id];

			$new_course = new Course();

			// Find out if this course has an eqv.
			if ($course_id = $this->get_transfer_course_eqv($transfer_course_id, false, $cur[$tfa->term_id]))
			{
				$new_course = new Course($course_id);
				$this->array_significant_courses[$course_id] = true;
			}



			$t_course = new Course();
			$t_course->subject_id = $cur[$tfb->subject_id];
			$t_course->course_num = $cur[$tfb->course_num];
			$t_course->course_id = $transfer_course_id;
			$t_course->bool_transfer = true;
			$t_course->institution_id = $institution_id;

			$new_course->bool_transfer = true;

			$new_course->course_transfer = $t_course;
			$new_course->grade = $cur[$tfb->grade];
			$t_course->grade = $cur[$tfb->grade];

			$new_course->hours_awarded = $cur[$tfb->hours_awarded];
			$t_course->hours_awarded = $cur[$tfb->hours_awarded];
			
			
		  // Was this course worth 0 hours but they didn't fail it?
			// If so, we need to set it to actually be 1 hour, and
			// indicate this is a "ghost hour."
			if (!in_array($new_course->grade, $retake_grades) 
			     && $new_course->hours_awarded == 0) 			
			{
			  $new_course->hours_awarded = 1;
			  $new_course->bool_ghost_hour = TRUE;
			  $t_course->hours_awarded = 1;
			  $t_course->bool_ghost_hour = TRUE;
			}						
			
			$new_course->bool_taken = true;
			$t_course->bool_taken = true;
			

			$new_course->term_id = $cur[$tfb->term_id];
			if (strstr($new_course->term_id, "9999"))
			{
				// was an unknown semester.  Let's set it lower so
				// it doesn't screw up my sorting.
				$new_course->term_id = 11111;
			}
      $t_course->term_id = $new_course->term_id;
			$new_course->display_status = "completed";

			$this->list_courses_taken->add($new_course);
		}
		//		print_pre($this->list_courses_taken->to_string());

	}



	
	/**
	 * Find a transfer eqv for this student, for this course in question.
	 *
	 */
	function get_transfer_course_eqv($transfer_course_id, $bool_ignore_unassigned = false, $require_valid_term_id = "")
	{
		
	  // First, make sure that this transfer course hasn't
		// been unassigned.  Do this by checking through
		// the student's courseListUnassignedTransferEQVs.
		$temp_course = new Course();
		$temp_course->course_id = $transfer_course_id;
		if ($bool_ignore_unassigned == false && $this->list_transfer_eqvs_unassigned->find_match($temp_course)) {
			// The transfer course in question has had its eqv removed,
			// so skip it!
			return false;
		}

    
	  // Let's pull the needed variables out of our settings, so we know what
		// to query, because this involves non-FlightPath tables.
		$tsettings = $GLOBALS["fp_system_settings"]["extra_tables"]["course_resources:transfer_eqv_per_student"];
		$tf = (object) $tsettings["fields"];  //Convert to object, makes it easier to work with.  
		$table_name = $tsettings["table_name"];

		
    $valid_term_line = "";
    if ($require_valid_term_id != "") {
      // We are requesting eqv's only from a particular valid term, so, amend
      // the query.
      $valid_term_line = "AND $tf->valid_term_id = $require_valid_term_id ";
    }
		
        
		// Does the supplied transfer course ID have an eqv?
		$res = $this->db->db_query("
			SELECT * FROM $table_name
			WHERE $tf->transfer_course_id = '?'
			AND $tf->student_id = '?'
			AND $tf->broken_id = '0'
			$valid_term_line 	", $transfer_course_id, $this->student_id);

		if ($cur = $this->db->db_fetch_array($res)) {
			return $cur[$tf->local_course_id];
		}
 
		return false;

	}
	
	
	function to_string()	{
		$rtn = "Student Information:\n";
		$rtn .= " Courses Taken:\n";
		$rtn .= $this->list_courses_taken->to_string();
		return $rtn;
	}

} // end class Student

?>