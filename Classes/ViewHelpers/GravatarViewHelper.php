<?php
namespace PwCommentsTeam\PwComments\ViewHelpers;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2015 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 */

/**
 * Gravatar Viewhelper
 *
 * @package PwCommentsTeam\PwComments
 */
class GravatarViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper
{

    /**
     * Generates a Gravatar link
     *
     * @param string $email The mail to create Gravatar link for
     * @param int $size The size of avatar in pixel
     * @param string $default The image to take if user has no Gravatar
     * @return string Link to Gravatar
     */
    public function render($email = null, $size = 100, $default = 'mm')
    {
        if ($email === null) {
            $email = $this->renderChildren();
        }

        $link = '.gravatar.com/avatar/';
        $hash = md5(strtolower(trim($email)));
        $domainHash = hexdec($hash[0]) % 3;
        $sizeParam = '?s=' . $size;
        $defaultParam = '&d=' . $default;
        return 'http://' . $domainHash . $link . $hash . $sizeParam . $defaultParam;
    }
}
