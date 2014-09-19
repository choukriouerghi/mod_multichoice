<?php

/**
 * @package    mod
 * @subpackage automultiplechoice
 * @copyright  2014 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod\automultiplechoice;

/**
 * Log the last action for each activity instance.
 *
 * @author François Gannaz <francois.gannaz@silecs.info>
 */
class Log {
    /**
     * @var int Instance ID.
     */
    protected $instanceId;

    /**
     * Constructor.
     *
     * @param int $instanceId
     * @throws Exception
     */
    public function __construct($instanceId) {
        $this->instanceId = (int) $instanceId;
        if ($this->instanceId <= 0) {
            throw new \Exception("Coding error, invalid instance ID.");
        }
    }

    /**
     * Constructor.
     *
     * @param int $instanceId
     * @throws Exception
     */
    static public function build($instanceId) {
        return new self($instanceId);
    }

    /**
     * Read the timestamp of the last action of this name.
     *
     * @global \moodle_database $DB
     * @param string $action
     * @return int
     */
    public function read($action) {
        global $DB;
        $this->actioncheck($action);
        $raw = $DB->get_field("automultiplechoice_log", 'actiontime', array('instanceid' => $this->instanceId, 'action' => $action), IGNORE_MISSING);
        return (int) $raw;
    }

    /**
     * Write the timestamp of this action.
     *
     * @global \moodle_database $DB
     * @param string $action
     * @param int $timestamp (opt) If not set, current timestamp.
     */
    public function write($action, $timestamp=null) {
        global $DB;
        $this->actioncheck($action);
        if ($timestamp === null) {
            $timestamp = $_SERVER['REQUEST_TIME'];
        }
        $record = array(
            'instanceid' => $this->instanceId,
            'action' => $action,
            'actiontime' => $timestamp,
        );
        $id = $DB->get_field("automultiplechoice_log", 'id', array('instanceid' => $this->instanceId, 'action' => $action));
        if ($id > 0) {
            $record['id'] = (int) $id;
            $DB->update_record("automultiplechoice_log", $record);
        } else {
            $DB->insert_record("automultiplechoice_log", $record, false);
        }
    }

    /**
     * @param array $actions Array of values among "amc", "pdf", "scoring", "upload".
     * @return array
     * @throws Exception
     */
    public function check($actions)
    {
        $messages = array();
        foreach ($actions as $action) {
            switch ($action) {
                case 'process':
                    $process = $this->read('process');
                    if ($process) {
                        $minutes = (int) \round(($_SERVER['REQUEST_TIME'] - $process)/60);
                        $messages[] = "AMC est déjà en cours d'exécution depuis $minutes minutes.";
                    }
                    break;
                case 'pdf':
                    $pdf = $this->read('pdf');
                    if ($this->read('upload') < $pdf) {
                        $messages[] = "Le PDF du QCM a été modifié après le dépôt des copies.";
                    }
                    if ($this->read('scoringsystem') > $pdf) {
                        $messages[] = "Le PDF du QCM a été modifié après le choix du barème.";
                    }
                    break;
                case 'grading':
                    /**
                     * @todo check grading
                     */
                    break;
                case 'correction':
                    /**
                     * @todo check correction/annote
                     */
                    break;
                default:
                    throw new \Exception("Unknown parameter '$action'.");
            }
        }
        return $messages;
    }

    protected function actioncheck($action) {
        $valid = array('process', 'pdf', 'scoringsystem', 'upload', 'grading', 'correction');
        if (in_array($action, $valid)) {
            return true;
        }
        throw new \Exception("L'action $action n'est pas valide.");
        return false;
    }
}