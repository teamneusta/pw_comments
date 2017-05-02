<?php
namespace PwCommentsTeam\PwComments\Tests\Unit\Utility;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2016 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016 Christian Wolfram <c.wolfram@chriwo.de>
 */
use PwCommentsTeam\PwComments\Utility\StringUtility;
use TYPO3\CMS\Core\Tests\UnitTestCase;

/**
 * Class StringUtilityTest
 *
 * @package PwCommentsTeam\PwComments
 */
class StringUtilityTest extends UnitTestCase
{
    /**
     * @var StringUtility
     */
    protected $testClass;

    /**
     *
     */
    public function setUp()
    {
        $this->testClass = new StringUtility();
    }

    /**
     *
     */
    public function tearDown()
    {
        unset($this->testClass);
    }

    /**
     * Test, if links in the comment could be convert to a-tag links
     *
     * @dataProvider commentMessageLinkDataProvider
     * @test
     * @param string $message
     * @param string $expected
     * @return void
     */
    public function linksCanBeCreated($message, $expected)
    {
        $this->assertEquals($expected, $this->testClass->createLinks($message));
    }

    /**
     * Data provider for convert urls to html code
     *
     * @return array
     */
    public function commentMessageLinkDataProvider()
    {
        return [
            'commentWithoutLink' => [
                'A simples commit without any other characters',
                'A simples commit without any other characters',
            ],
            'commentLinkWithoutWww' => [
                'A simples commit with a http://typo3.org any other characters',
                'A simples commit with a <a href="http://typo3.org">http://typo3.org</a> any other characters',
            ],
            'commentLinkWithWww' => [
                'A simples commit with a http://www.typo3.org any other characters',
                'A simples commit with a <a href="http://www.typo3.org">http://www.typo3.org</a> any other characters',
            ],
            'commentLinkWithHttps' => [
                'A simples commit with a https://typo3.org any other characters',
                'A simples commit with a <a href="https://typo3.org">https://typo3.org</a> any other characters',
            ],
            'commentLinkWithHttpsWww' => [
                'A simples commit with a https://www.typo3.org any other characters',
                'A simples commit with a <a href="https://www.typo3.org">https://www.typo3.org</a> any other characters',
            ],
            'commentLinkWithParams' => [
                'A simples commit with a http://sourceforge.net/project/showfiles.php?group_id=20391 any other characters',
                'A simples commit with a <a href="http://sourceforge.net/project/showfiles.php?group_id=20391">http://sourceforge.net/project/showfiles.php?group_id=20391</a> any other characters',
            ]
        ];
    }

    /**
     * Test, if more lines could be convert to two lines
     *
     * @dataProvider dataCommentLineProvider
     * @param $message
     * @param $expected
     * @test
     * @return void
     */
    public function convertTrippleLines($message, $expected)
    {
        $this->assertEquals($expected, $this->testClass->convertTrippleLinesToDoubleLines($message));
    }

    /**
     * Data provider to convert 3 lines into 2 lines
     *
     * @return array
     */
    public function dataCommentLineProvider()
    {
        return [
            'simpleComment' => [
                'A simples commit without any other characters',
                'A simples commit without any other characters',
            ],
            'commentWithTwoLines' => [
                'A comment ' . "\r\n\r\n" . 'with two lines',
                'A comment ' . "\r\n\r\n" . 'with two lines'
            ],
            'commentWithTrippleLines' => [
                'A comment ' . "\r\n\r\n\r\n" . 'with three lines',
                'A comment ' . "\r\n\r\n" . 'with three lines'
            ],
            'commentWithManyLines' => [
                'A comment ' . "\r\n\r\n\r\n\r\n" . 'with two lines',
                'A comment ' . "\r\n\r\n" . 'with two lines'
            ]
        ];
    }

    /**
     * Test, if a comment could be correct prepared bevore write into database
     *
     * @dataProvider dataCommentPrepareProvider
     * @param string $message
     * @param boolean $allowLink
     * @param string $expected
     * @test
     * @return void
     */
    public function commentCanBePreparedForDatabase($message, $allowLink, $expected)
    {
        $this->assertEquals($expected, $this->testClass->prepareCommentMessage($message, $allowLink));
    }

    /**
     * Data provider for commentCanBePreparedForDatabase
     *
     * @return array
     */
    public function dataCommentPrepareProvider()
    {
        return [
            'simpleComment' => [
                'A simples commit without any other characters',
                false,
                'A simples commit without any other characters',
            ],
            'commentWithNewLines' => [
                'A comment' . "\r\n\r\n\r\n" . 'with three lines',
                false,
                'A comment' . "\r\n\r\n" . 'with three lines'
            ],
            'commentWithHtmlCode' => [
                '<h1/>HTML comment</h1><br /> and br break for a new line',
                false,
                '&lt;h1/&gt;HTML comment&lt;/h1&gt;&lt;br /&gt; and br break for a new line'
            ],
            'commentAllowLinks' => [
                '<h1/>HTML comment</h1>' . "\r\n\r\n\r\n" . 'A simples commit with a https://www.typo3.org any other characters',
                true,
                '&lt;h1/&gt;HTML comment&lt;/h1&gt;' . "\r\n\r\n" . 'A simples commit with a <a href="https://www.typo3.org">https://www.typo3.org</a> any other characters',
            ],
            'commentDisallowLinks' => [
                '<h1/>HTML comment</h1>' . "\r\n\r\n\r\n" . 'A simples commit with a https://www.typo3.org any other characters',
                false,
                '&lt;h1/&gt;HTML comment&lt;/h1&gt;' . "\r\n\r\n" . 'A simples commit with a https://www.typo3.org any other characters',
            ]
        ];
    }
}
