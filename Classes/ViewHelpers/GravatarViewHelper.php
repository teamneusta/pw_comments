<?php
namespace T3\PwComments\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;
/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */
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
 * @package T3\PwComments
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
    final const GRAVATARURI = 'https://www.gravatar.com/avatar/';

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

        $this->registerArgument('email', 'string', 'The mail to create Gravatar link for', true);
        $this->registerArgument('size', 'integer', 'The size of avatar in pixel', false, 100);
        $this->registerArgument('default', 'string', 'The image to take if user has no Gravatar', false, 'mm');
    }

    /**
     * Generates a Gravatar image tag
     *
     * @return string html image tag with the gravatar image uri
     */
    public function render()
    {
        $uriParts = [
            md5(strtolower(trim((string) $this->arguments['email']))),
            '?s=' . $this->arguments['size'],
            '&d=' . $this->arguments['default']
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
     * @return string full uri of garvatar with uri params
     */
    protected function getGravatarSrc(array $uriParts)
    {
        return self::GRAVATARURI . implode('', $uriParts);
    }
}
