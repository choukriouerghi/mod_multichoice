<?php
/**
 * @package    mod
 * @subpackage automultiplechoice
 * @copyright  2013 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod\automultiplechoice;

require_once __DIR__ . '/AmcProcess.php';
require_once dirname(__DIR__) . '/locallib.php';

class AmcProcessGrade extends AmcProcess
{
    const PATH_AMC_CSV = '/exports/scores.csv';
    const PATH_FULL_CSV = '/exports/scores_names.csv';
    const CSV_SEPARATOR = ';';

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
        $res = $this->shellExec('auto-multiple-choice prepare', $parameters);
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
        $res = $this->shellExec('auto-multiple-choice note', $parameters);
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
            '--output', $pre . self::PATH_AMC_CSV,
            '--option-out', 'encodage=UTF-8',
            '--option-out', 'columns=student.copy,student.key,student.name',
            '--option-out', 'decimal=,',
            '--option-out', 'ticked=',
            '--option-out', 'separateur=' . self::CSV_SEPARATOR,
            );
        $res = $this->shellExec('auto-multiple-choice export', $parameters);
        if ($res) {
            $this->log('export', 'scoring.csv');
        }
        return $res;
    }

    /**
     * low-level Shell-executes 'amc annote'
     * fills the cr/corrections/jpg directory with individual annotated copies
     * @return bool
     */
    public function amcAnnote() {
        $pre = $this->workdir;
        $parameters = array(
            '--projet', $pre,
            '--ch-sign', '4',
            '--cr', $pre.'/cr',
            '--data', $pre.'/data',
            '--id-file',  '',
            '--taille-max', '1000x1500',
            '--qualite', '100',
            '--line-width', '2',
            '--indicatives', '1',
            '--symbols', '0-0:none/#000000,0-1:circle/#ff0000,1-0:mark/#ff0000,1-1:mark/#00ff00',
            '--position', 'marge',
            '--pointsize-nl', '60',
            '--verdict', '%(ID) Note: %s/%m (score total : %S/%M)',
            '--verdict-question', '%s/%m',
            '--no-rtl',
            '--changes-only'
        );
        $res = $this->shellExec('auto-multiple-choice annote', $parameters, true);
        if ($res) {
            $this->log('annote', '');
        }
        return $res;
    }

     /**
     * lowl-level Shell-executes 'amc regroupe'
     * fills the cr/corrections/pdf directory with a global pdf file for all copies
     * @return bool
     */
    public function amcRegroupe() {
        $pre = $this->workdir;
        $parameters = array(
            '--id-file', "",
            '--no-compose',
            '--projet',  $pre,
            '--sujet', $pre.'/prepare-sujet.pdf',
            '--data', $pre.'/data',
            '--tex-src', $pre.'/prepare-source.txt',
            '--with', 'xelatex',
            '--filter', 'plain',
            '--filtered-source', $pre.'/prepare-source_filtered.tex',
            '--n-copies', (string) $this->quizz->amcparams->copies,
            '--progression-id', 'regroupe',
            '--progression', '1',
            '--modele', '',
            '--fich-noms', '%PROJET/',
            '--noms-encodage', 'UTF-8',
            '--csv-build-name', '(nom|surname) (prenom|name)',
            '--single-output', 'corrections_tous.pdf',
            '--sort', 'n',
            '--register',
            '--no-force-ascii'
        );
        $res = $this->shellExec('auto-multiple-choice regroupe', $parameters, true);
        if ($res) {
            $this->log('regroupe', '');
        }
        return $res;
    }


    /**
     * Return an array of students with added fields for identified users.
     *
     * @return boolean Success?
     */
    public function writeFileWithIdentifiedStudents() {
        $filename = $this->workdir . self::PATH_AMC_CSV;
        if (!is_readable($filename)) {
            return false;
        }
        $handle = fopen($filename, 'r');
        if (!$handle) {
            return false;
        }
        $output = fopen($this->workdir . self::PATH_FULL_CSV, 'w');
        if (!$output) {
            return false;
        }

        $header = fgetcsv($handle, 1024, self::CSV_SEPARATOR);
        if (!$header) {
            return false;
        }
        $getCol = array_flip($header);
        $header[] = 'firstname';
        $header[] = 'lastname';
        fputcsv($output, $header, self::CSV_SEPARATOR);

        while (($data = fgetcsv($handle, 1024, self::CSV_SEPARATOR)) !== FALSE) {
            $idnumber = $data[$getCol['student.number']];
            $user = null;
            if ($idnumber) {
                $user = getStudentByIdNumber($idnumber);
            }
            if ($user) {
                $data[$getCol['Name']] = fullname($user);
                $data[] = $user->firstname;
                $data[] = $user->lastname;
                //$data[] = $user->email;
            } else {
                $data[] = '';
                $data[] = '';
            }
            fputcsv($output, $data, self::CSV_SEPARATOR);
        }
        fclose($handle);
        fclose($output);
        return true;
    }
}