<?php
/* vim:set softtabstop=4 shiftwidth=4 expandtab: */
/**
 *
 * LICENSE: GNU General Public License, version 2 (GPLv2)
 * Copyright 2001 - 2013 Ampache.org
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License v2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

/**
 * Rating class
 *
 * This tracks ratings for songs, albums and artists.
 *
 */
class Rating extends database_object
{
    // Public variables
    public $id;        // The ID of the object rated
    public $type;        // The type of object we want

    /**
     * Constructor
     * This is run every time a new object is created, and requires
     * the id and type of object that we need to pull the rating for
     */
    public function __construct($id, $type)
    {
        $this->id = intval($id);
        $this->type = $type;

        return true;

    } // Constructor

    /**
     * gc
     *
     * Remove ratings for items that no longer exist.
     */
    public static function gc()
    {
        foreach (array('song', 'album', 'artist', 'video') as $object_type) {
            Dba::write("DELETE FROM `rating` USING `rating` LEFT JOIN `$object_type` ON `$object_type`.`id` = `rating`.`object_id` WHERE `object_type` = '$object_type' AND `$object_type`.`id` IS NULL");
        }
    }

    /**
      * build_cache
     * This attempts to get everything we'll need for this page load in a
     * single query, saving on connection overhead
     */
    public static function build_cache($type, $ids)
    {
        if (!is_array($ids) OR !count($ids)) { return false; }

        $ratings = array();
        $user_ratings = array();

        $idlist = '(' . implode(',', $ids) . ')';
        $sql = "SELECT `rating`, `object_id` FROM `rating` " .
            "WHERE `user` = ? AND `object_id` IN $idlist " .
            "AND `object_type` = ?";
        $db_results = Dba::read($sql, array($GLOBALS['user']->id, $type));

        while ($row = Dba::fetch_assoc($db_results)) {
            $user_ratings[$row['object_id']] = $row['rating'];
        }

        $sql = "SELECT AVG(`rating`) as `rating`, `object_id` FROM " .
            "`rating` WHERE `object_id` IN $idlist AND " .
            "`object_type` = ? GROUP BY `object_id`";
        $db_results = Dba::read($sql, array($type));

        while ($row = Dba::fetch_assoc($db_results)) {
            $ratings[$row['object_id']] = $row['rating'];
        }

        foreach ($ids as $id) {
            // First store the user-specific rating
            if (!isset($user_ratings[$id])) {
                $rating = 0;
            } else {
                $rating = intval($user_ratings[$id]);
            }
            parent::add_to_cache('rating_' . $type . '_user' . $GLOBALS['user']->id, $id, $rating);

            // Then store the average
            if (!isset($ratings[$id])) {
                $rating = 0;
            } else {
                $rating = round($ratings[$id], 1);
            }
            parent::add_to_cache('rating_' . $type . '_all', $id, $rating);
        }

        return true;

    } // build_cache

    /**
     * get_user_rating
     * Get a user's rating.  If no userid is passed in, we use the currently
     * logged in user.
     */
     public function get_user_rating($user_id = null)
     {
        if (is_null($user_id)) {
            $user_id = $GLOBALS['user']->id;
        }

        $key = 'rating_' . $type . '_user' . $user_id;
        if (parent::is_cached($key, $this->id)) {
            return parent::get_from_cache($key, $this->id);
        }

        $sql = "SELECT `rating` FROM `rating` WHERE `user` = ? ".
            "AND `object_id` = ? AND `object_type` = ?";
        $db_results = Dba::read($sql, array($user_id, $this->id, $type));

        $rating = 0;

        if ($results = Dba::fetch_assoc($db_results)) {
            $rating = $results['rating'];
        }

        parent::add_to_cache($key, $this->id, $rating);
        return $rating;

    } // get_user_rating

    /**
     * get_average_rating
     * Get the floored average rating of what everyone has rated this object
     * as. This is shown if there is no personal rating.
     */
    public function get_average_rating()
    {
        if (parent::is_cached('rating_' . $type . '_all', $id)) {
            return parent::get_from_cache('rating_' . $type . '_user', $id);
        }

        $sql = "SELECT AVG(`rating`) as `rating` FROM `rating` WHERE " .
            "`object_id` = ? AND `object_type` = ?";
        $db_results = Dba::read($sql, array($this->id, $this->type));

        $results = Dba::fetch_assoc($db_results);

        parent::add_to_cache('rating_' . $type . '_all', $id, $results['rating']);
        return $results['rating'];

    } // get_average_rating

    /**
     * get_highest_sql
     * Get highest sql
     */
    public static function get_highest_sql($type)
    {
        $type = Stats::validate_type($type);
        $sql = "SELECT `object_id` as `id`, AVG(`rating`) AS `rating` FROM rating" .
            " WHERE object_type = '" . $type . "'" .
            " GROUP BY object_id ORDER BY `rating` ";
        return $sql;
    }

    /**
     * get_highest
     * Get objects with the highest average rating.
     */
    public static function get_highest($type, $count='', $offset='')
    {
        if (!$count) {
            $count = Config::get('popular_threshold');
        }
        $count = intval($count);
        if (!$offset) {
            $limit = $count;
        } else {
            $limit = intval($offset) . "," . $count;
        }

        /* Select Top objects counting by # of rows */
        $sql = self::get_highest_sql($type);
        $sql .= "DESC LIMIT $limit";
        $db_results = Dba::read($sql, array($type));

        $results = array();

        while ($row = Dba::fetch_assoc($db_results)) {
            $results[] = $row['id'];
        }

        return $results;

    }

    /**
     * set_rating
     * This function sets the rating for the current object.
     * If no userid is passed in, we use the currently logged in user.
     */
    public function set_rating($rating, $user_id = null)
    {
        if (is_null($user_id)) {
            $user_id = $GLOBALS['user']->id;
        }
        $user_id = intval($user_id);

        debug_event('Rating', "Setting rating for $type $id to $rating", 5);

        // If score is -1, then remove rating
        if ($rating == '-1') {
            $sql = "DELETE FROM `rating` WHERE " .
                "`object_id` = ? AND " .
                "`object_type` = ? AND " .
                "`user` = ?";
            $params = array($this->id, $this->type, $user_id);
        } else {
            $sql = "REPLACE INTO `rating` " .
            "(`object_id`, `object_type`, `rating`, `user`) " .
            "VALUES (?, ?, ?, ?)";
            $params = array($this->id, $this->type, $rating, $user_id);
        }
        $db_results = Dba::write($sql, $params);

        parent::add_to_cache('rating_' . $type . '_user' . $user_id, $id, $rating);

        foreach (Plugin::get_plugins('save_rating') as $plugin_name) {
            $plugin = new Plugin($plugin_name);
            if ($plugin->load()) {
                $plugin->_plugin->save_rating($this, $rating);
            }
        }

        return true;

    } // set_rating

    /**
     * show
     * This takes an id and a type and displays the rating if ratings are
     * enabled.  If $static is true, the rating won't be editable.
     */
    public static function show($object_id, $type, $static=false)
    {
        // If ratings aren't enabled don't do anything
        if (!Config::get('ratings')) { return false; }

        $rating = new Rating($object_id, $type);

        if ($static) {
            require Config::get('prefix') . '/templates/show_static_object_rating.inc.php';
        } else {
            require Config::get('prefix') . '/templates/show_object_rating.inc.php';
        }

    } // show

} //end rating class
