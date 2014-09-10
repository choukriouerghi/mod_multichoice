<?php
/**
 * @package    mod
 * @subpackage automultiplechoice
 * @copyright  2013 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod\automultiplechoice;

require_once __DIR__ . '/ScoringSystem.php';

global $DB;
/* @var $DB \moodle_database */

/**
 * QuestionList behaves as an array.
 *
 * <code>
 * $ql = \mod\automultiplechoice\QuestionList::fromJson($json);
 * if ($ql) {
 *     echo $ql[0]['score'];
 * }
 * </code>
 */
class QuestionList implements \Countable, \ArrayAccess
{
    /**
     * @var array array of array('questionid' => (integer), 'score' => (float), 'scoring' => "b=1,e=0..."
     */
    public $questions = array();

    /**
     * @var array List of values among: "qnumber", "score", "qscore".
     */
    public $errors = array();

    /**
     * Get the DB records with added score/scoring fields.
     *
     * @global \moodle_database $DB
     * @param integer $scoringSetId (opt) If given, question will have a 'scoring' field.
     * @param boolean $includeSections (opt)
     * @return array of "question+multichoice" records (objects from the DB) with an additional "score", "scoring" fields.
     */
    public function getRecords($scoringSetId=null, $includeSections=false) {
        if (!$this->questions) {
            return array();
        }
        $records = $this->getRawRecords();
        if (isset($scoringSetId)) {
            $scoringSet = ScoringSystem::read()->getScoringSet($scoringSetId);
        } else {
            $scoringSet = null;
        }
        $callback = function ($q) use ($records, $scoringSet, $includeSections) {
            if (isset($q['questionid'])) {
                $r = $records[$q['questionid']];
                $r->score = (double) $q['score'];
                if ($scoringSet) {
                    $rule = $scoringSet->findMatchingRule($r);
                    if ($rule) {
                        $r->scoring = $rule->getExpression($r);
                    } else {
                        $r->scoring = ''; // default AMC scoring (incomplete set of rules)
                    }
                }
                return $r;
            } else if (is_string($q)) {
                if ($includeSections) {
                    return $q;
                }
            }
        };
        return array_filter(array_map($callback, $this->questions));
    }

    /**
     * Validate the question against the quizz parameters.
     *
     * @param \mod\automultiplechoice\Quizz $quizz
     * @return boolean
     */
    public function validate(Quizz $quizz) {
        $this->errors = array();
        if (count($this->getIds()) != $quizz->qnumber) {
            $this->errors['qnumber'] = 'validateql_wrong_number';
        }
        $scores = $this->getScores();
        if (array_sum($scores) != $quizz->score) {
            $this->errors['score'] = 'validateql_wrong_sum';
        }
        if (in_array(0, $scores)) {
            $this->errors['qscore'] = 'validateql_wrong_score';
        }
        if ($this->errors) {
            return false;
        }

        // deleted questions?
        $validIds = array();
        foreach ($this->getRawRecords() as $r) {
            $validIds[] = $this->getById($r->id);
        }
        if (count($validIds) != count($this->getIds())) {
            $this->questions = $validIds;
            $this->errors['qnumber'] = 'validateql_deletedquestions';
            return false;
        }

        return true;
    }

    /**
     * Return the JSON serialization of this instance.
     *
     * @return string
     */
    public function toJson()
    {
        if (empty($this->questions)) {
            return '';
        }
        return json_encode(
                array_map(
                        function ($v) { if (is_array($v)) { return array_values($v); } else { return $v; } },
                        $this->questions
                )
        );
    }

    /**
     * Return a new instance from a serialized JSON instance.
     *
     * @param string $json
     * @return QuestionList
     */
    public static function fromJson($json)
    {
        $new = new self();
        $decoded = json_decode($json);
        if (!empty($decoded) && is_array($decoded)) {
            foreach ($decoded as $q) {
                if (is_string($q)) {
                    $new->questions[] = $q;
                } else {
                    $new->questions[] = array(
                        'questionid' => (int) $q[0],
                        'score' => (double) $q[1],
                        'scoring' => (isset($q[2]) ? $q[2] : ''),
                    );
                }
            }
        }
        return $new;
    }

    /**
     * Read $_POST[$fieldname] and return a new instance.
     *
     * @return QuestionList
     */
    public static function fromForm($fieldname) {
        if (!isset($_POST[$fieldname]) || empty($_POST[$fieldname]['id'])) {
            return null;
        }
        $new = new self();
        for ($i = 0; $i < count($_POST[$fieldname]['id']); $i++) {
            if (ctype_digit($_POST[$fieldname]['id'][$i])) {
                $new->questions[] = array(
                    'questionid' => (int) $_POST[$fieldname]['id'][$i],
                    'score' => (double) str_replace(',', '.', $_POST[$fieldname]['score'][$i]),
                    'scoring' => isset($_POST[$fieldname]['scoring']) ? $_POST[$fieldname]['scoring'][$i] : '',
                );
            } else {
                $new->questions[] = $_POST[$fieldname]['id'][$i];
            }
        }
        return $new;
    }

    /**
     * Checks if the list contains a given question (or one of the given list).
     *
     * @param integer|array $questionids
     * @return boolean
     */
    public function contains($questionids) {
        if (is_array($questionids)) {
            $lookup = array_flip($questionids);
        } else {
            $lookup = array($questionids => true);
        }
        foreach ($this->questions as $q) {
            if (isset($q['questionid']) && isset($lookup[$q['questionid']])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Find a question by its id.
     *
     * @param type $id
     * @return array
     */
    public function getById($id) {
        foreach ($this->questions as $q) {
            if (isset($q['questionid']) && $q['questionid'] == $id) {
                return $q;
            }
        }
        return null;
    }

    /**
     * Get the records from the DB, with get_records_sql().
     *
     * @global \moodle_database $DB
     * @return array
     */
    protected function getRawRecords() {
        global $DB, $CFG;
        $ids = $this->getIds();
        list ($cond, $params) = $DB->get_in_or_equal($ids);
        if ($CFG->version >= 2013111800) {
            $qtable = 'qtype_multichoice_options';
            $qfield = 'questionid';
        } else {
            $qtable = 'question_multichoice';
            $qfield = 'question';
        }
        return $DB->get_records_sql(
                'SELECT q.*, qc.single '
                . 'FROM {question} q INNER JOIN {' . $qtable . "} qc ON qc.{$qfield}=q.id "
                . 'WHERE q.id ' . $cond,
                $params
        );
    }

    /**
     * Return the list of question.id
     *
     * @return array of integers
     */
    private function getIds() {
        $ids = array();
        foreach ($this->questions as $q) {
            if (isset($q['questionid'])) {
                $ids[] = $q['questionid'];
            }
        }
        return $ids;
    }

    /**
     * Return the list of scores
     *
     * @return array of integers
     */
    private function getScores() {
        $scores = array();
        foreach ($this->questions as $q) {
            if (isset($q['questionid'])) {
                $scores[] = $q['score'];
            }
        }
        return $scores;
    }

    // Implement Countable
    /**
     * Number of questions.
     *
     * @return int Count
     */
    public function count() {
        return count($this->getIds());
    }

    // Implement ArrayAccess
    public function offsetSet($offset, $value) {
    }
    public function offsetUnset($offset) {
    }
    public function offsetExists($offset) {
        return isset($this->questions[$offset]);
    }
    public function offsetGet($offset) {
        return isset($this->questions[$offset]) ? $this->questions[$offset] : null;
    }
}
