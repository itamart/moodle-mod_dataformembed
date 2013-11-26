<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    mod
 * @subpackage dataformembed
 * @copyright  2013 Itamar Tzadok {@link http://substantialmethods.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die;

/**
 * @global object
 * @param object $dataformembed
 * @return bool|int
 */
function dataformembed_add_instance($dataformembed) {
    global $DB;

    $dataformembed->name = get_string('modulename','dataformembed');
    $dataformembed->timemodified = time();

    return $DB->insert_record("dataformembed", $dataformembed);
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @param object $dataformembed
 * @return bool
 */
function dataformembed_update_instance($dataformembed) {
    global $DB;

    $dataformembed->name = get_string('modulename','dataformembed');
    $dataformembed->timemodified = time();
    $dataformembed->id = $dataformembed->instance;
    if (empty($dataformembed->dataform)) {
        $dataformembed->view = 0;
        $dataformembed->filter = 0;
    }

    return $DB->update_record("dataformembed", $dataformembed);
}

/**
 * @global object
 * @param int $id
 * @return bool
 */
function dataformembed_delete_instance($id) {
    global $DB;

    if (!$dataformembed = $DB->get_record("dataformembed", array("id"=>$id))) {
        return false;
    }

    $result = true;

    if (!$DB->delete_records("dataformembed", array("id"=>$dataformembed->id))) {
        $result = false;
    }

    return $result;
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 * See get_array_of_activities() in course/lib.php
 *
 * @global object
 * @param object $coursemodule
 * @return object|null
 */
function dataformembed_get_coursemodule_info($coursemodule) {
    global $DB;

    if ($dataformembed = $DB->get_record('dataformembed', array('id'=>$coursemodule->instance), 'id, name, dataform, view, filter')) {
        if (empty($dataformembed->name)) {
            // dataformembed name missing, fix it
            $dataformembed->name = "dataformembed{$dataformembed->id}";
            $DB->set_field('dataformembed', 'name', $dataformembed->name, array('id'=>$dataformembed->id));
        }
        $info = new stdClass();
        $info->extra = '';           
        $info->name  = $dataformembed->name;
        return $info;
    } else {
        return null;
    }
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 * See get_array_of_activities() in course/lib.php
 *
 * @global object
 * @param object $coursemodule
 * @return object|null
 */
function dataformembed_cm_info_view(cm_info $cm) {
    global $DB, $CFG;

    if (!$dataformembed = $DB->get_record('dataformembed', array('id'=>$cm->instance), 'id, name, dataform, view, filter, embed, style')) {
        return;
    }

    // We must have at least dataform id and view id
    if (empty($dataformembed->dataform) or empty($dataformembed->view)) {
        return;
    }

    // Sanity check in case the designated dataform has been deleted
    if ($dataformembed->dataform and !$DB->record_exists('dataform', array('id' => $dataformembed->dataform))) {
        return;
    }
          
    // Sanity check in case the designated view has been deleted
    if ($dataformembed->view and !$DB->record_exists('dataform_views', array('id' => $dataformembed->view))) {
        return;
    }
          
    // if embed create the container
    if (!empty($dataformembed->embed)) {
        $dataurl = new moodle_url(
            '/mod/dataform/embed.php',
            array('d' => $dataformembed->dataform, 'view' => $dataformembed->view)
        );
        
        // filter
        if (!empty($dataformembed->filter)) {
            $dataurl->param('filter', $dataformembed->filter);
        }
        
        // styles
        $styles = dataformembed_parse_css_style($dataformembed);
        if (!isset($styles['width'])) {
            $styles['width'] = '100%;';
        }
        if (!isset($styles['height'])) {
            $styles['height'] = '200px;';
        }
        if (!isset($styles['border'])) {
            $styles['border'] = '0;';
        }
        $stylestr = implode('',array_map(function($a, $b){return "$a: $b";}, array_keys($styles), $styles));            
        
        // The iframe attr
        $params = array('src' => $dataurl, 'style' => $stylestr);
        
        // Add scrolling attr
        if (!empty($styles['overflow']) and $styles['overflow'] == 'hidden;') {
            $params['scrolling'] = 'no';
        }             

        // The iframe
        $content = html_writer::tag(
            'iframe',
            null,
            $params  
        );

    // Not embedding
    } else {
    
        // Set a dataform object with guest autologin
        require_once("$CFG->dirroot/mod/dataform/mod_class.php");
        if ($df = new dataform($dataformembed->dataform, null, true)) {
            if ($view = $df->get_view_from_id($dataformembed->view)) {
                if (!empty($dataformembed->filter)) {
                    $view->set_filter(array('id' => $dataformembed->filter));
                }        
                $params = array(
                        'js' => true,
                        'css' => true,
                        'modjs' => true,
                        'completion' => true,
                        'comments' => true,
                        'nologin' => true,
                );        
                $pageoutput = $df->set_page('external', $params);
                
                $view->set_content();
                $viewcontent = $view->display(array('tohtml' => true));
                $content = "$pageoutput\n$viewcontent";
            }
        }
    }

    if (!empty($content)) {
        $cm->set_content($content);
    }
}

/**
 * @return array
 */
function dataformembed_parse_css_style($dataformembed) {
    $styles = array();
    if (!empty($dataformembed->style) and $arr = explode(';', $dataformembed->style)) {
        foreach ($arr as $rule) {
            if ($rule = trim($rule) and strpos($rule, ':')) {
                list($attribute, $value) = array_map('trim', explode(':', $rule));
                if ($value !== '') {
                    $styles[$attribute] = "$value;";
                }
            }
        }                
    }    
    return $styles;
}

/**
 * @return array
 */
function dataformembed_get_view_actions() {
    return array();
}

/**
 * @return array
 */
function dataformembed_get_post_actions() {
    return array();
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 *
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function dataformembed_reset_userdata($data) {
    return array();
}

/**
 * Returns all other caps used in module
 *
 * @return array
 */
function dataformembed_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

/**
 * @uses FEATURE_IDNUMBER
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return bool|null True if module supports feature, false if not, null if doesn't know
 */
function dataformembed_supports($feature) {
    switch($feature) {
        case FEATURE_IDNUMBER:                return false;
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return false;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_MOD_ARCHETYPE:           return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_NO_VIEW_LINK:            return true;

        default: return null;
    }
}

