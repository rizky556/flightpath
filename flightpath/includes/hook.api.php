 <?php
 
 /**
  * @file
  * Lists all available hooks within FlightPath's core code.
  * 
  * This script is not meant to be included and used in FlightPath,
  * it is simply for documentation of how the various hooks work.
  * 
  * In each example, you may use a hook by replacing the word "hook"
  * with the name of your custom module.
  * 
  * For example, hook_menu() might be my_module_menu().  FlightPath
  * will automatically begin using the hooks in your module, once the
  * module is enabled.
 */
 
 
 
/**
 * Alter forms which are created using the Form API
 * 
 * @param &$form
 *   This is the actual form array, retrieved from the form's callback
 *   function.  It should ALWAYS be passed by reference, so that the
 *   changes you make to it will carry through.
 * @param $form_id
 *   This is the "form_id" of the form-- usually the name of the callback
 *   function which created the form.  It can be used to identify forms
 *   before you attempt to take action.  
 * 
 */
function hook_form_alter(&$form, $form_id) {
  if ($form_id == "example_form_callback") {
    $form["new_element"] = array(
      // ...
    );
  }
}
 
 
/**
 * Validates form submissions from the Form API
 * 
 * This function can be named anything you want (or can be considered optional).
 * If named the same thing as the form callback, it will be utilized automatically.
 * For example, if you form is named my_form, then if you have a validate function
 * named my_form_validate, it will be called automatically.
 * 
 * Since $form_state is passed by reference, you may make changes to this array, and
 * those changes will carry through to other validate functions (defined with #validate_handlers)
 * and then to the submit handler(s).
 * 
 * @see hook_submit($form, &$form_state)
 */
function hook_validate($form, &$form_state) {
  $age = $form_state["values"]["age"];
  if ($age < 18) {
    form_error("age", "Sorry, you must be 18 or older to submit this form.");
    return;
  }
}

 
/**
 * Handle submissions from the Form API
 * 
 * Like hook_validate, this function (if it exists) is called after you submit a form,
 * and after the validation completes without errors.  This is where you should act on
 * the submission, confident that it has passed validation, and the values in $form_state
 * are in the final state.
 * 
 * @see hook_validate($form, &$form_state)
 */
function hook_submit($form, &$form_state) {
  $values = $form_state["values"];
  db_query("INSERT INTO example (f_name) ('?') ", $values["name"]);
}

 

/**
 * Handle needed database updates when user updates a module.
 * 
 * Modules can specify which schema their tables are using in their .info file.
 * if the module, in a later version, changes the table it writes to, it should increment
 * the schema number, whic causes the user to be notified (on the modules page) that they need
 * to run the DB updates.
 * 
 * When the DB updates are run, every module implementing this function will be called
 * (it is expected to be in a .install file, instead of the .module file, though both get
 * included by the system first).
 *
 * In this function, the developer can see what schema they are coming FROM, and make table
 * or other alterations based on that information.
 * 
 */
function hook_update($old_schema, $new_schema) {
  if ($new_schema < 4) {
    db_query("ALTER TABLE my_example_table ...... ");
  }
}


 
/**
 * Allows each module to perform actions when the cache is cleared.
 * 
 * If your custom module needs to clear its own cache, for example,
 * then you should implement this hook and place your module-specific
 * code inside.
 *
 */
function hook_clear_cache() {
  db_query("DELETE FROM mymodule_cache_tables WHERE 1");
}
 
 
 
 
 
 
 