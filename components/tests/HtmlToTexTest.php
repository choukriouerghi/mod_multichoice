<?php
/**
 * @license http://www.gnu.org/licenses/gpl-3.0.html  GNU GPL v3
 * @author François Gannaz <francois.gannaz@silecs.info>
 */

require dirname(__DIR__) . '/HtmlToTex.php';

class HtmlToTexTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideSimpleFragments
     */
    public function testSimpleFragments($html, $tex)
    {
        $conv = new HtmlToTex();
        $conv->loadFragment($html);
        $this->assertEquals($tex, $conv->toTex());
    }

    public function provideSimpleFragments()
    {
        return [
            [
                'Bonjour, $f^2(x)$',
                'Bonjour, \$f\^{}2(x)\$',
            ],
            [
                "a\n\nb",
                'a  b',
            ],
            [
                '<p>Bonjour, <b>moi</b> !</p><div class="xx"><i class="none">Pas $toi$</i>, ^moi_même &amp; et c\'est tout&nbsp;!</div>',
                '

Bonjour, \textbf{moi} !



\textit{Pas \$toi\$}, \^{}moi\_même \& et c\'est tout !

'
            ],
            [
                "<ul><li>a</li>\n<li>b</li></ul>",
                '\begin{itemize}\item[]a \item[]b\end{itemize}',
            ],
        ];
    }
}