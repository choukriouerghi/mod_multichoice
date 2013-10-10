<?php
/**
 * @package    mod
 * @subpackage automultiplechoice
 * @copyright  2013 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod\automultiplechoice;

class AmcProcess
{
    /**
     * @var Quizz Contains notably an 'amcparams' attribute.
     */
    protected $quizz;

    protected $codelength = 0;

    public $workdir;
    public $relworkdir;

    /**
     * @var array
     */
    protected $errors = array();

    /**
     * Constructor
     *
     * @param Quizz $quizz
     */
    public function __construct($quizz) {
        global $CFG;
        $this->quizz = $quizz;

        $dir = sprintf('automultiplechoice_%05d', $this->quizz->id);
        $this->workdir = $CFG->dataroot . '/local/automultiplechoice/' . $dir;
        $this->relworkdir = '/local/automultiplechoice/' . $dir;

        $this->codelength = (int) get_config('mod_automultiplechoice', 'amccodelength');
        /**
         * @todo error if codelength == 0
         */
    }

    /**
     * Return the errors of the last command.
     * @return array
     */
    public function getLastErrors() {
        return $this->errors;
    }

    /**
     * Compute the whole source file content, by merging header and questions blocks
     * @return string file content
     */
    public function getSourceAmctxt() {
        $res = $this->getHeaderAmctxt();

        foreach ($questions = $this->quizz->questions->getRecords() as $question) {
            $res .= $this->questionToFileAmctxt($question);

        }
        return $res;
    }

    /**
     * Save the source file
     * @param type $filename
     */
    public function saveAmctxt() {

        $this->initWorkdir();
        $filename = $this->workdir . "/prepare-source.txt";
        $res = file_put_contents($filename, $this->getSourceAmctxt());
        if ($res) {
            $this->log('prepare:source', 'prepare-source.txt');
        }
        return $res;
    }

    /**
     * Shell-executes 'amc prepare' for creating pdf files
     * @return bool
     */
    public function createPdf() {
        $pre = $this->workdir;
        $res = $this->shellExec('auto-multiple-choice prepare', array(
            '--n-copies', (string) $this->quizz->amcparams->copies,
            '--with', 'xelatex',
            '--filter', 'plain',
            '--mode', 's[sc]',
            '--prefix', $pre,
            '--out-corrige', $pre . '/prepare-corrige.pdf',
            '--out-sujet', $pre . '/prepare-sujet.pdf',
            '--out-catalog', $pre . '/prepare-catalog.pdf',
            '--out-calage', $pre . '/prepare-calage.xy',
            '--latex-stdout',
            $pre . '/prepare-source.txt'
            ));
        if ($res) {
            $this->log('prepare:pdf', 'prepare-catalog.pdf prepare-corrige.pdf prepare-sujet.pdf');
        }
        return $res;
    }

    /**
     * Shell-executes 'amc meptex'
     * @return bool
     */
    public function amcMeptex() {
        $pre = $this->workdir;
        $res = $this->shellExec(
                'auto-multiple-choice meptex',
                array(
                    '--data', $pre . '/data',
                    '--progression-id', 'MEP',
                    '--progression', '1',
                    '--src', $pre . '/prepare-calage.xy',
                )
        );
        if ($res) {
            $this->log('meptex', '');
        }
        return $res;
    }

    /**
     * Shell-executes 'amc getimages'
     * @param string $scanfile name, uploaded by the user
     * @return bool
     */
    public function amcGetimages($scanfile) {
        $pre = $this->workdir;
        $scanlist = $pre . '/scanlist';
        if (file_exists($scanlist)) {
            unlink($scanlist);
        }
        $mask = $pre . "/scans/*.ppm"; // delete all previous ppm files
        array_map('unlink', glob( $mask ));

        $res = $this->shellExec('auto-multiple-choice getimages', array(
            '--progression-id', 'analyse',
            '--vector-density', '250',
            '--orientation', 'portrait',
            '--list', $scanlist,
            '--copy-to', $pre . '/scans/',
            $scanfile
            ), true);
        if ($res) {
            $nscans = count(file($scanlist));
            $this->log('getimages', $nscans . ' pages');
            return $nscans;
        }
        return $res;
    }

    /**
     * Shell-executes 'amc analyse'
     * @param bool $multiple (see AMC) if multiple copies of the same sheet are possible
     * @return bool
     */

    public function amcAnalyse($multiple = true) {
        $pre = $this->workdir;
        $scanlist = $pre . '/scanlist';
        $parammultiple = '--' . ($multiple ? '' : 'no-') . 'multiple';
        $parameters = array(
            $parammultiple,
            '--tol-marque', '0.2,0.2',
            '--prop', '0.8',
            '--bw-threshold', '0.6',
            '--progression-id' , 'analyse',
            '--progression', '1',
            '--n-procs', '0',
            '--data', $pre . '/data',
            '--projet', $pre,
            '--cr', $pre . '/cr',
            '--liste-fichiers', $scanlist,
            '--no-ignore-red',
            );
        //echo "\n<br> auto-multiple-choice analyse " . join (' ', $parameters) . "\n<br>";
        $res = $this->shellExec('auto-multiple-choice analyse', $parameters, true);
        if ($res) {
            $this->log('analyse', 'OK.');
        }
        return $res;
    }


    /**
     * Shell-executes 'amc prepare' for extracting grading scale (Bareme)
     * @return bool
     */
    public function amcPrepareBareme() {
        $pre = $this->workdir;
        $parameters = array(
            '--n-copies', (string) $this->quizz->amcparams->copies,
            '--with', 'xelatex',
            '--filter', 'plain',
            '--mode', 'b',
            '--data', $pre . '/data',
            '--filtered-source', $pre . '/prepare-source_filtered.tex',
            '--progression-id', 'bareme',
            '--progression', '1',
            $pre . '/prepare-source.txt'
            );
        $res = $this->shellExec('auto-multiple-choice prepare', $parameters, true);
        if ($res) {
            $this->log('prepare:bareme', 'OK.');
        }
        return $res;
    }

    /**
     * Shell-executes 'amc note'
     * @return bool
     */
    public function amcNote() {
        $pre = $this->workdir;
        $parameters = array(
            '--data', $pre . '/data',
            '--progression-id', 'notation',
            '--progression', '1',
            '--seuil', '0.5',
            '--grain', '0.5',
            '--arrondi', 'inf',
            '--notemax', '20',
            '--plafond',
            '--notemin', '',
            '--postcorrect-student', '', //FIXME inutile ?
            '--postcorrect-copy', '',    //FIXME inutile ?
            );
        $res = $this->shellExec('auto-multiple-choice note', $parameters, true);
        if ($res) {
            $this->log('note', 'OK.');
        }
        return $res;
    }

    /**
     * Shell-executes 'amc export' to get a csv file
     * @return bool
     */
    public function amcExport() {
        $pre = $this->workdir;
        $parameters = array(
            '--module', 'CSV',
            '--data', $pre . '/data',
            '--useall', '',
            '--sort', 'n',
            '--fich-noms', '%PROJET/',
            '--noms-encodage', 'UTF-8',
            '--csv-build-name', '(nom|surname) (prenom|name)',
            '--no-rtl',
            '--output', $pre . '/exports/scoring.csv',
            '--option-out', 'encodage=UTF-8',
            '--option-out', 'columns=student.copy,student.key,student.name',
            '--option-out', 'decimal=,',
            '--option-out', 'ticked=',
            '--option-out', 'separateur=;',
            );
        $res = $this->shellExec('auto-multiple-choice export', $parameters, true);
        if ($res) {
            $this->log('export', 'scoring.csv');
        }
        return $res;
    }


    /**
     * log processed action
     * @param string $action ('prepare'...)
     * @param string $msg
     */
    public function log($action, $msg) {
        $url = '/mod/automultiplechoice/view.php?a='. $this->quizz->id;
        $cm = get_coursemodule_from_instance('automultiplechoice', $this->quizz->id, $this->quizz->course, false, MUST_EXIST);
        add_to_log($this->quizz->course, 'automultiplechoice', $action, $url, $msg, $cm->id, 0);
        return true;
    }

    public function lastlog($action) {
        global $DB;

        $cm = get_coursemodule_from_instance('automultiplechoice', $this->quizz->id, $this->quizz->course, false, MUST_EXIST);
        $sql = 'SELECT FROM_UNIXTIME(time) FROM {log} WHERE action=? AND cmid=? ORDER BY time DESC LIMIT 1';
        $res = $DB->get_field_sql($sql, array($action, $cm->id), IGNORE_MISSING);
        return $res;
    }


    protected function initWorkdir() {
        global $CFG;

        if ( ! file_exists($this->workdir) || ! is_dir($this->workdir)) {
            $templatedir = get_config('mod_automultiplechoice', 'amctemplate');
            $diag = $this->shellExec('cp', array('-r', $templatedir, $this->workdir));
        }
    }

    /**
     *
     * @param string $cmd
     * @param array $params List of strings.
     * @return boolean Success?
     */
    protected function shellExec($cmd, $params, $output=false) {
        $escapedCmd = escapeshellcmd($cmd);
        $escapedParams = array_map('escapeshellarg', $params);
        $shellCmd = $escapedCmd . " " . join(" ", $escapedParams);
        $lines = array();
        $returnVal = 0;
        exec($shellCmd, $lines, $returnVal);

        if ($returnVal === 0) {
            return true;
        } else {
            /**
             * @todo Fill $this->errors
             */
            if ($output) {
                $this->shellOutput($shellCmd, $returnVal, $lines);
            }
            return false;
        }
    }

    /**
     * Displays a block containing the shell output
     *
     * @param string $cmd
     * @param integer $returnVal shell return value
     * @param array $lines output lines to be displayed
     */
    protected function shellOutput($cmd, $returnVal, $lines) {
        if (get_config('core', 'debugdisplay') == 0) {
            return false;
        }
        $html = '<pre style="margin:2px; padding:2px; border:1px solid grey;">' . " \n";
        $html .= $cmd . " \n";
        $i=0;
        foreach ($lines as $line) {
            $i++;
            $html .= sprintf("%03d.", $i) . " " . $line . "\n";
        }
        $html .= "Return value = <b>" . $returnVal. "</b\n";
        $html .= "</pre> \n";
        debugging($html, DEBUG_NORMAL);
    }

    /**
     * Turns a question into a formatted string, in the AMC-txt (aka plain) format
     * @param object $question record from the 'question' table
     * @return string
     */
    protected function questionToFileAmctxt($question) {
        global $DB;

        $answerstext = '';
        $trueanswers = 0;
        $answers = $DB->get_records('question_answers', array('question' => $question->id));
        foreach ($answers as $answer) {
            $trueanswer = ($answer->fraction > 0);
            $answerstext .= ($trueanswer ? '+' : '-') . " " . strip_tags($answer->answer) . "\n";
            $trueanswers += (int) $trueanswer;
        }
		$options = ($this->quizz->amcparams->shufflea ? '' : '[ordered]');
        $questiontext = ($trueanswers == 1 ? '*' : '**')
                . $options
                . ($question->scoring ? '[' . $question->scoring . ']' : '')
                . ' ' . $question->name . "\n" . strip_tags($question->questiontext) . "\n";

        return $questiontext . $answerstext . "\n";
    }

    /**
     * Computes the header block of the source file
     * @return string header block of the AMC-TXT file
     */
    protected function getHeaderAmctxt() {
        $descr = preg_replace('/\n\s*\n/', "\n", $this->quizz->description);
		$shuffleq = (int) $this->quizz->amcparams->shuffleq;
		$separatesheet = (int) $this->quizz->amcparams->separatesheet;

        return "
# AMC-TXT source
PaperSize: A4
Lang: FR
Code: {$this->codelength}
ShuffleQuestions: {$shuffleq}
SeparateAnswerSheet: {$separatesheet}
Title: {$this->quizz->name}
Presentation: {$descr}

";
    }
}
