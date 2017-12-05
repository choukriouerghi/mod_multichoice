<?php

namespace mod_automultiplechoice\output;

defined('MOODLE_INTERNAL') || die();

class view_association implements \renderable, \templatable {
    /**
     * The auto multiple choice quiz.
     *
     * @var \mod_automultiplechoice\local\models\quiz
     */
    protected $quiz;

    /**
     *
     * @var array a set of usefull data
     */
    protected $data;

    /**
     * Contruct
     *
     * @param mod_automultiplechoice/local/models/quiz $quiz A quiz
     * @param array $data A set of usefull data
     */
    public function __construct($quiz, $data) {
        $this->quiz = $quiz;
        $this->data = $data;
    }

    /**
     * Prepare data for use in a template
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output) {

        $associationmodes = [];
        foreach ($this->data['associationmodes'] as $value => $label) {
            $selected = $this->data['associationmode'] === $value;
            $associationmodes[] = ['value' => $value, 'label' => $label, 'selected' => $selected];
        }

        $usermodes = [];
        foreach ($this->data['usermodes'] as $value => $label) {
            $selected = $this->data['usermode'] === $value;
            $usermodes[] = ['value' => $value, 'label' => $label, 'selected' => $selected];
        }

        $currentpage =  $this->data['pager']['page'];

        // use core paging_bar
        $pagingbar = new \paging_bar(
            $this->data['pager']['pagecount'],
            $currentpage,
            $this->data['pager']['perpage'],
            $this->data['pager']['url']
        );

        $content = [
            'quiz' => $this->quiz,
            'errors' => $this->data['errors'],
            'showerrors' => $this->data['showerrors'],
            'isrelaunch' => $this->data['isrelaunch'],
            'nbcopyauto' => $this->data['nbcopyauto'],
            'nbcopymanual' => $this->data['nbcopymanual'],
            'nbcopyunknown' => $this->data['nbcopyunknown'],
            'associationmodes' => $associationmodes,
            'usermodes' => $usermodes,
            'usersdata' => $this->data['usersdata'], // data to display (allready paginated)
            'pager' => $output->render($pagingbar),
            'currentpage' => $currentpage,
            'addemptyoption' => $this->data['associationmode'] === 'unknown' || $this->data['associationmode'] === 'manual'
        ];

        return $content;
    }
}
