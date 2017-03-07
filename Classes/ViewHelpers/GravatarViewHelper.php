<?php
namespace PwCommentsTeam\PwComments\ViewHelpers;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2017 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */
use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * Gravatar Viewhelper
 *
 * =Examples=
 *
 * <code title="Simple">
 * <pw:gravatar email="your-email@domain.com"/>
 * </code>
 * <output>
 * <img src="https://www.gravatar.com/avatar/19acb70929e62bf326957428f65331f3?s=100&d=mm" />
 * </output>
 *
 * <code title"Alternative with params">
 * <pw:gravatar email="your-email@domain.com" size="20" default="mm"/>
 * </code>
 * <output>
 * <img src="https://www.gravatar.com/avatar/19acb70929e62bf326957428f65331f3?s=20&d=mm" />
 * </output>
 *
 * <code title"Inline notation">
 * {pw:gravatar(email: 'your-email@domain.com' size: '20' default: 'mm'}
 * </code>
 * <output>
 * <img src="https://www.gravatar.com/avatar/19acb70929e62bf326957428f65331f3?s=20&d=mm" />
 * </output>
 *
 * @package PwCommentsTeam\PwComments
 */
class GravatarViewHelper extends AbstractTagBasedViewHelper
{
    /**
     * @var string
     */
    protected $tagName = 'img';

    /**
     * Gravatar URI
     *
     * @var string
     */
    const GRAVATARURI = 'https://www.gravatar.com/avatar/';

    /**
     * Initialize arguments
     *
     * @return void
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerUniversalTagAttributes();
        $this->registerTagAttribute('alt', 'string', 'Specifies an alternate text for an image', false);
    }

    /**
     * Generates a Gravatar image tag
     *
     * @param string $email The mail to create Gravatar link for
     * @param int $size The size of avatar in pixel
     * @param string $default The image to take if user has no Gravatar
     * @return string html image tag with the gravatar image uri
     */
    public function render($email = null, $size = 100, $default = 'mm')
    {
        if ($email === null) {
            $email = $this->renderChildren();
        }

        $uriParts = [
            md5(strtolower(trim($email))),
            '?s=' . $size,
            '&d=' . $default
        ];

        $this->tag->addAttribute('src', $this->getGravatarSrc($uriParts));

        // The alt-attribute is mandatory to have valid html-code, therefore add it even if it is empty
        if (empty($this->arguments['alt'])) {
            $this->tag->addAttribute('alt', '');
        }

        return $this->tag->render();
    }

    /**
     * Returns the full garvatar uri
     *
     * @param array $uriParts
     * @return string full uri of garvatar with uri params
     */
    protected function getGravatarSrc(array $uriParts)
    {
        return self::GRAVATARURI . implode('', $uriParts);
    }
}
