<?php
namespace T3\PwComments\Utility;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 *  |     2023 Malek Olabi <m.olabi@neusta.de>
 */

use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Cookie Utility
 *
 * @package T3\PwComments
 */
class Cookie
{
    /** Cookie Prefix */
    final const COOKIE_PREFIX = 'tx_pwcomments_';
    /** Lifetime of cookie in days */
    final const COOKIE_LIFETIME_DAYS = 365;

    /**
     * Get cookie value
     *
     * @param string $key
     */
    public function get($key): ?string
    {
        if (isset($_COOKIE[self::COOKIE_PREFIX . $key])) {
            return $_COOKIE[self::COOKIE_PREFIX . $key];
        }
        return null;
    }

    /**
     * Set cookie value
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public function set($key, $value): void
    {
        $cookieExpireDate = time() + self::COOKIE_LIFETIME_DAYS * 24 * 60 * 60;
        setcookie(
            self::COOKIE_PREFIX . $key,
            $value,
            $cookieExpireDate,
            '/',
            $this->getCookieDomain(),
            isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieSecure']) && $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieSecure'] > 0,
            isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieHttpOnly']) && $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieHttpOnly'] == 1
        );
    }

    /**
     * Gets the domain to be used on setting cookies. The information is
     * taken from the value in $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain']
     *
     * @return string The domain to be used on setting cookies
     */
    protected function getCookieDomain()
    {
        $typo3Mode = ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isFrontend() ? 'FE' : 'BE';
        $result = '';
        $cookieDomain = $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'] ?? null;
        if (!empty($GLOBALS['TYPO3_CONF_VARS'][$typo3Mode]['cookieDomain'])) {
            $cookieDomain = $GLOBALS['TYPO3_CONF_VARS'][$typo3Mode]['cookieDomain'];
        }
        if ($cookieDomain) {
            if ($cookieDomain[0] === '/') {
                $match = [];
                $matchCnt = @preg_match(
                    $cookieDomain,
                    (string) GeneralUtility::getIndpEnv('TYPO3_HOST_ONLY'),
                    $match
                );
                if ($matchCnt !== false) {
                    $result = $match[0];
                }
            } else {
                $result = $cookieDomain;
            }
        }
        return $result;
    }
}
