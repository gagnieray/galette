<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette's news
 *
 * PHP version 5
 *
 * Copyright © 2011 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  News
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-11-11
 */

/** @ignore */
require_once GALETTE_GAPI_PATH . '/apiClient.php';
require_once GALETTE_GAPI_PATH . '/contrib/apiPlusService.php';

/**
 * News class for galette
 *
 * @category  News
 * @name      GaletteNews
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-11-11
 */
class GaletteNews
{
    const TWITTER_API_URL = 'https://api.twitter.com/1/statuses/user_timeline/%userid.xml?count=%count&include_rts=%rt';

    private $_cache_filename = 'news.cache';

    private $_show = 5;
    private $_rt = 'false';

    private $_twitter_url = null;

    private $_tweets;
    private $_gplus;

    /**
    * Default constructor
    */
    public function __construct()
    {
        //let's build twitter api url with correct variables
        $this->_twitter_url = preg_replace(
            array(
                '/%userid/',
                '/%count/',
                '/%rt/'
            ),
            array(
                GALETTE_TWITTER,
                $this->_show,
                $this->_rt
            ),
            self::TWITTER_API_URL
        );
        $url = $this->_twitter_url;
        if ( GALETTE_MODE === 'DEV' || !$this->_checkCache() ) {
            $this->_makeCache();
        } else {
            $this->_loadCache();
        }
    }

    /**
     * Check if cache is valid
     *
     * @return boolean
     */
    private function _checkCache()
    {
        $cfile = $this->_getCacheFilename();
        if (file_exists($cfile) ) {
            $dformat = 'Y-m-d H:i:s';
            $mdate = DateTime::createFromFormat(
                $dformat,
                date(
                    $dformat,
                    filemtime($cfile)
                )
            );
            $expire = $mdate->add(new DateInterval('PT5H'));
            $now = new DateTime();
            $has_expired = $now > $expire;
            return !$has_expired;
        } else {
            return false;
        }
    }

    /**
     * Creates/update the cache
     *
     * @return boolean
     */
    private function _makeCache($load = true)
    {
        if ( $load === true ) {
            $this->_parseTweets();
            $this->_parseGplus();
        }

        $cfile = $this->_getCacheFilename();
        $stream = fopen($cfile, 'w+');
        fwrite(
            $stream,
            serialize(
                array(
                    'tweets' => $this->_tweets,
                    'gplus'  => $this->_gplus
                )
            )
        );
        fclose($stream);
        return false;
    }

    /**
     * Loads entries from cache
     *
     * @return void
     */
    private function _loadCache()
    {
        $cfile = $this->_getCacheFilename();
        $data = unserialize(file_get_contents($cfile));

        $refresh_cache = false;
        $this->_tweets = $data['tweets'];
        //check if tweets were cached
        if ( !is_array($this->_tweets) || count($this->_tweets) == 0 ) {
            $this->_parseTweets();
            $refresh_cache = true;
        }

        $this->_gplus = $data['gplus'];
        //check if gplus posts were cached
        if ( !is_array($this->_gplus) || count($this->_gplus) == 0 ) {
            $this->_parseGplus();
            $refresh_cache = true;
        }

        if ( $refresh_cache === true ) {
            $this->_makeCache(false);
        }
    }

    /**
     * Complete path to cache file
     *
     * @return string
     */
    private function _getCacheFilename()
    {
        return GALETTE_CACHE_DIR . $this->_cache_filename;
    }

    /**
     * Parse tweets
     *
     * @return void
     */
    private function _parseTweets()
    {
        global $log;

        $xml = simplexml_load_file($this->_twitter_url);

        if ( !$xml ) {
            $log->log(
                'An error occured trying to retrieve Tweets',
                PEAR_LOG_ERR
            );
            $this->_tweets = array();
            return false;
        }

        //search and replace:
        //- urls,
        //- @names,
        //- #hashtags
        $patterns = array(
            '@(?i)\b((?:[a-z][\w-]+:(?:/{1,3}|[a-z0-9%])|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))@',
            '/@(\w+)/',
            '/\s+#(\w+)/'
        );
        $replacements = array(
            '<a href="$1">$1</a>',
            '<a href="http://twitter.com/$1">@$1</a>',
            ' <a href="http://search.twitter.com/search?q=%23$1">#$1</a>'
        );

        $tweets = array();
        foreach ( $xml->status as $status ) {
            $tweets[] = preg_replace(
                $patterns,
                $replacements,
                (string)$status->text
            );
        }

        $this->_tweets = $tweets;
    }

    /**
     * Parse Google+ posts
     *
     * @return void
     */
    private function _parseGplus()
    {
        $gclient = new apiClient();
        $gclient->setApplicationName("Galette's Google+");
        $gclient->setDeveloperKey(GALETTE_GAPI_KEY);
        $plus = new apiPlusService($gclient);

        $optParams = array('maxResults' => $this->_show);
        $activities = $plus->activities->listActivities(
            GALETTE_GPLUS,
            'public',
            $optParams
        );

        $gposts = array();
        foreach ($activities['items'] as $activity) {
            $gposts[$activity['url']] = $activity['title'];
        }

        $this->_gplus = $gposts;
    }

    /**
     * Get tweets
     *
     * @return array
     */
    public function getTweets()
    {
        return $this->_tweets;
    }

    /**
     * Get Google+ posts
     *
     * @return array
     */
    public function getPlusPosts()
    {
        return $this->_gplus;
    }
}
?>