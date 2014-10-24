<?php
/**
 * @package    mod_automultiplechoice
 * @copyright  2013-2014 Silecs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Description of HtmlHelper
 *
 * @author François Gannaz <francois.gannaz@silecs.info>
 */
class HtmlHelper {
    /**
     *
     * @param string $buttonText
     * @param integer $quizzid
     * @param string $targetpage
     * @param string $action
     * @param string $checks (opt)
     * @return string HTML
     */
    public static function buttonWithAjaxCheck($buttonText, $quizzid, $targetpage, $action, $checks = "") {
        $checklock = json_encode(array('a' => $quizzid, 'actions' => $checks));
        $button = '<form action="' . htmlspecialchars(new moodle_url("/mod/automultiplechoice/$targetpage.php", array('a' => $quizzid)))
            . '" method="post" '
            . ($checks ? 'class="checklock" data-checklock="' . htmlspecialchars($checklock) . '">' : '>') . '
        <p>
            <input type="hidden" name="action" value="%s" />
            <button type="submit">%s</button>
        </p>
        </form>';
        return sprintf($button, htmlspecialchars($action), $buttonText);
    }

    public static function printFormFullQuestions(\mod\automultiplechoice\Quizz $quizz) {
        $scoringSet = mod\automultiplechoice\ScoringSystem::read()->getScoringSet($quizz->amcparams->scoringset);
        $select = mod\automultiplechoice\ScoringSystem::read()->toHtmlSelect('amc[scoringset]', $quizz->amcparams->scoringset);
        echo '<form action="" method="post" name="qselect">
        <input name="a" value="' . $quizz->id . '" type="hidden" />';
        echo '<input name="qnumber" value="' . $quizz->qnumber . '" type="hidden" id="quizz-qnumber"/>';

        echo '<table class="flexible generaltable quizz-summary" id="params-quizz"><tbody>';
        echo '<tr><th>' . get_string('score', 'automultiplechoice') . '</th>'
            . '<td><input type="text" id="expected-total-score" class="qscore" name="score" value='
            . $quizz->score . ' /></td></tr>';
        echo '<tr><th>' . get_string('scoringset', 'automultiplechoice') . '</th>';
        echo '<td>' . $select . '<div id="scoringset_desc"></div></td></tr>';
        echo '</tbody></table>';
        echo '<table class="flexible boxaligncenter generaltable" id="questions-selected">';
        echo '<thead><tr><th>#</th>'
                . '<th>' . get_string('qscore', 'automultiplechoice')
                . '</th><th>' . get_string('qtitle', 'automultiplechoice')
                . '<div><button type="button" id="toggle-answers">Afficher/masquer les réponses</button></div>'
                . '</th></tr></thead>';
        echo '<tbody>';

        $k = 1;
        $nbline = 1;
        foreach ($quizz->questions as $q) {
            echo '<tr>';
            if ($q->getType() === 'section') {
                echo '<td colspan="3">' . htmlspecialchars($q->name)
                    . '<div class="question-answers">' . format_text($q->description, FORMAT_HTML) . '</div>'
                    . '<input name="q[score][]" value="" type="hidden" />';
            } else {
                echo '<td>' . $k . '</td>
                    <td class="q-score">
                        <input name="q[score][]" type="text" class="qscore" value="' . $q->score . '" />
                    </td>
                    <td><div><b>' . format_string($q->name) . '</b></div><div>'. format_string($q->questiontext) . '</div>'
                        . HtmlHelper::listAnswers($q);
                $k++;
            }
            echo "</td>\n</tr>\n";
            $nbline++;
        }
        if ($nbline%2) {
            echo '<tr></tr>';
        }
        echo '<tr>'
            . '<td></td>'
            . '<th><span id="computed-total-score">' . $quizz->score . '</span> / '
            . '<span id="total-score">' . $quizz->score . '</span></th>'
            . '<td>';
        echo '<button type="button" id="scoring-distribution">Répartir les points</button>';
        echo '</td></tr>';
        echo '</tbody></table>';
        echo '<div><button type="submit">' . get_string('savechanges') . '</button></div>';
        echo "</form>\n";
    }

    public static function printTableQuizz(\mod\automultiplechoice\Quizz $quizz, $rows = array('instructions', 'description', 'comment', 'qnumber', 'score', 'scoringset'))
    {
        $realQNumber = $quizz->questions->count();
        $scoringSet = mod\automultiplechoice\ScoringSystem::read()->getScoringSet($quizz->amcparams->scoringset);
        echo '<table class="flexible generaltable quizz-summary">';
        echo '<tbody>';
        $rowCount = 0;
        foreach ($rows as $row) {
            $rowCount++;
            $tr = '<tr class="r' . ($rowCount % 2) . '"><th>';
            switch ($row) {
                case 'instructions':
                    echo $tr . get_string('instructions', 'automultiplechoice') . '</th>'
                        . '<td>' . format_text($quizz->amcparams->instructionsprefix, $quizz->amcparams->instructionsprefixformat) . '</td></tr>';
                    break;
                case 'description':
                    echo $tr . get_string('description', 'automultiplechoice') . '</th>'
                        . '<td>' . format_text($quizz->description, $quizz->descriptionformat) . '</td></tr>';
                    break;
                case 'comment':
                    if ($quizz->comment) {
                        echo $tr . get_string('comment', 'automultiplechoice') . '</th><td>' . format_string($quizz->comment) . '</td></tr>';
                    } else {
                        $rowCount--;
                    }
                    break;
                case 'qnumber':
                    echo $tr . get_string('qnumber', 'automultiplechoice') . '</th><td>'
                            . ($quizz->qnumber == $realQNumber ? $quizz->qnumber : "<span class=\"score-mismatch\">$realQNumber / {$quizz->qnumber}</span>")
                            . '</td></tr>';
                    break;
                case 'score':
                    echo $tr . get_string('score', 'automultiplechoice') . '</th><td id="expected-total-score">' . $quizz->score . '</td></tr>';
                    break;
                case 'scoringset':
                    echo $tr . get_string('scoringset', 'automultiplechoice') . '</th><td>'
                            . '<div><strong>' . format_string($scoringSet->name) . '</strong></div>'
                            . '<div>' . nl2br(format_string($scoringSet->description)) . '</div>'
                            . '</td></tr>';
                    break;
                default:
                    throw new Exception("Coding error, unknown row $row.");
            }
        }
        echo '</tbody></table>';
    }

    protected static function listAnswers($question) {
        global $DB;
        $answers = $DB->get_recordset('question_answers', array('question' => $question->id));
        $html = '<div class="question-answers"><ul>';
        foreach ($answers as $answer) {
            $html .= '<li class="answer-' . ($answer->fraction > 0 ? 'right' : 'wrong') . '">'
                    . format_string($answer->answer) . "</li>\n";
        }
        $html .= "</ul></div>\n";
        return $html;
    }
}
