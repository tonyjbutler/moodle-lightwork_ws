<?php

/**
 * Classes for handling querying and manipulating LW Rubric records.
 *
 * PHP version 5
 *
 * @package LW_Rubric
 * @version $Id$
 * @author Dean Stringer <deans@waikato.ac.nz>
 * @author David Vega Morales <davidvm@waikato.ac.nz>
 * @author Yoke Chui <yokec@waikato.ac.nz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * notes:
 * o we dont do any user validation, the constructor is passed a userid and we assume the calling class has
 *   access to be querying the data for that user
 */

/**
 * Class for handling querying and manipulating individual Rubric records. Checking their
 * properties, inserting, updating deleting etc.
 */
require_once('lw_common.php');

class LW_Rubric {

    private $fields = array('id','timemodified','activity','activitytype','xmltext','complete','deleted','lwid');
    public  $error;
    public  $helper;

    public function __construct() {
        global $CFG;
        $this->error = new LW_Error();
        $this->helper = new LW_Common();
        $this->lwid = 0;
        $this->xmltext = '';
        $this->activity = 0;
        $this->activitytype = 0;
        $fields = array('id','timemodified','activity','activitytype','xmltext','complete','deleted','lwid');
        foreach ($fields as $key) {
            $this->fields[$key] = null;
        }
    }

    /**
     * Load a LW Rubric by activity
     * @param  activity  integer id of the rubric activity id
     * @param  activitytype  integer id of the rubric activity type
     * @return integer   rubric record id, null if not found
     */
    function get_byactivity($activity, $activitytype) {
    	global $DB;
        if (!is_numeric($activity) || ($activity<1) || !is_numeric($activitytype) || ($activitytype<1)) {
            return null;
        }
        if ($rubric = $DB->get_record('lw_rubric',array('activity'=>$activity,'activitytype'=>$activitytype))) {
            $this->id = $rubric->id;
            $this->xmltext = $rubric->xmltext;
            $this->activity = $rubric->activity;
            $this->activitytype = $rubric->activitytype;
            $this->complete = $rubric->complete;
            $this->deleted = $rubric->deleted;
            $this->timemodified = $rubric->timemodified;
            $this->lwid = $rubric->lwid;
            return $this->id;
        }
        return null;
    }

    /**
     * Load a LW Rubric by id
     * @param   id  integer id of the rubric we want to fetch
     * @return integer	rubric record id, null if not found
     */
    function get_byid($id) {
    	global $DB;
        if (!is_numeric($id) || ($id<1)) {
            return null;
        }
        if ($rubric = $DB->get_record('lw_rubric', array('id'=>$id))) {
            $this->id = $id;
            $this->xmltext = $rubric->xmltext;
            $this->activity = $rubric->activity;
            $this->activitytype = $rubric->activitytype;
            $this->complete = $rubric->complete;
            $this->deleted = $rubric->deleted;
            $this->timemodified = $rubric->timemodified;
            $this->lwid = $rubric->lwid;
            return $id;
        }
        return null;
    }

    /**
     * Save a rubric by modifiedtime (from LW client)
     * @return   integer  saved rubric id if save ok, null if not
     */
    function savebytimemodified($timemodified) {
        global $DB;
        $this->lwid = $this->id;

        if (!$this->activity) {
            return false; // cant save without an activity id
        }
        if (!$this->xmltext) {
            return false; // cant save without some xml
        }
        if (!$this->activitytype) {
            return false; // cant save without an activity type
        }
        if (!$this->lwid) {
            return false; // cant save without a lwid
        }
        //Get the corresponding records for the foregn keys
		$rubric = $DB->get_record('lw_rubric',array('lwid'=>$this->lwid,'activity'=>$this->activity));
        $assignment = $DB->get_record('assignment', array('id'=>$this->activity));
        // a timemodified value of 0 indicates a database insert. A value greater than 0 indicates an update.
        if ($timemodified == 0) {
            // check for referential integrity before doing insert
            if (!$rubric && $assignment) {
                // insert a new rubric
                $rubric = new object();
                $rubric->xmltext = $this->xmltext;
                $rubric->activity = clean_param($this->activity, PARAM_INT);
                $rubric->activitytype = clean_param($this->activitytype, PARAM_INT);
                $rubric->complete = clean_param($this->complete, PARAM_INT);
                $rubric->deleted = clean_param($this->deleted, PARAM_INT);
                $rubric->timemodified = time();
                $rubric->lwid = clean_param($this->lwid, PARAM_INT);
                if ($newid = $DB->insert_record('lw_rubric',$rubric,true)) {
                    $this->id = $newid;
                    $this->timemodified = $rubric->timemodified;
                    return $newid;
                } else {
                    // cannot insert a rubric for some reason
                    $this->error->add_error('Rubric', $rubric->lwid, 'cannotinsertother');
                    return false;
                }
            } else {
            	$this->error->add_error('Rubric', $rubric->lwid, 'cannotinsertkeyviolation');
                return false;
            }
            
        } else {
        	// check for referential integrity before doing update
            if ($rubric && $assignment) {

                $this->id = $rubric->id;
                $rubric->xmltext = $this->xmltext;
                $rubric->complete = clean_param($this->complete, PARAM_INT);
                $rubric->deleted = clean_param($this->deleted, PARAM_INT);
                $rubric->timemodified = time();
                if ($DB->update_record('lw_rubric',$rubric)) {
                    $this->timemodified = $rubric->timemodified;
                    return $this->id;
                }
                else {
                    $this->error->add_error('Rubric', $this->activity, 'errsysupdaterubric');
                    return false;
                }
            } else {
                $this->error->add_error('Rubric', $this->lwid, 'norubricfound');
                return false;
            }
        }
    }

    /**
     * Save a rubric as long as we have an activity id and some xml
     * @return	integer  saved rubric id if save ok, null if not
     */
    function save() {
    	global $DB;
        if (!$this->activity) {
            return null; // cant save without an activity id
        }
        if (!$this->xmltext) {
            return null; // cant save without some xml
        }
        if (!$this->activitytype) {
            return null; // cant save without an activity type
        }

        $rubric = $DB->get_record('lw_rubric',array('activity'=>$this->activity,'activitytype'=>$this->activitytype));
        $assignment = $DB->get_record('assignment', array('id'=>$this->activity));

        if ($rubric && $assignment) {
            $this->id = $rubric->id;
            $rubric->xmltext = $this->xmltext;
            if ($DB->update_record('lw_rubric',$rubric)) {
                return $this->id;
            }
        }
        elseif (!$rubric && $assignment) {
            $rubric = new object();
            $rubric->xmltext = $this->xmltext;
            $rubric->activity = clean_param($this->activity, PARAM_INT);
            $rubric->activitytype = clean_param($this->activitytype, PARAM_INT);
            $rubric->complete = 0;
            $rubric->deleted = 0;
            $rubric->timemodified = time();
            if ($newid = $DB->insert_record('lw_rubric',$rubric,true)) {
                $this->id = $newid;
                return $newid;
            } else {
                error_log('could not save rubric for activity id: '.$this->activity);
            }
        }
        return null;
    }

    /**
     * Render a rubric out as HTML for display in Moodle
     * @return   string    HTML string
     * @todo to be implemented
     */
    function to_html() {
        return "<p>rubric</p>";
    }

    /**
     * Render a rubric out as PDF for display in Moodle
     * @return   binary    PDF stream
     * @todo to be implemented
     */
    function to_pdf() {
        return "PDF";
    }

}

?>