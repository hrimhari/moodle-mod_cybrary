<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Cybrary subscription manager.
 *
 * @package    mod_cybrary
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_cybrary;

defined('MOODLE_INTERNAL') || die();

/**
 * Cybrary subscription manager.
 *
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscriptions {

    /**
     * The status value for an unsubscribed discussion.
     *
     * @var int
     */
    const CYBRARY_DISCUSSION_UNSUBSCRIBED = -1;

    /**
     * The subscription cache for cybraries.
     *
     * The first level key is the user ID
     * The second level is the cybrary ID
     * The Value then is bool for subscribed of not.
     *
     * @var array[] An array of arrays.
     */
    protected static $cybrarycache = array();

    /**
     * The list of cybraries which have been wholly retrieved for the cybrary subscription cache.
     *
     * This allows for prior caching of an entire cybrary to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $fetchedcybraries = array();

    /**
     * The subscription cache for cybrary discussions.
     *
     * The first level key is the user ID
     * The second level is the cybrary ID
     * The third level key is the discussion ID
     * The value is then the users preference (int)
     *
     * @var array[]
     */
    protected static $cybrarydiscussioncache = array();

    /**
     * The list of cybraries which have been wholly retrieved for the cybrary discussion subscription cache.
     *
     * This allows for prior caching of an entire cybrary to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $discussionfetchedcybraries = array();

    /**
     * Whether a user is subscribed to this cybrary, or a discussion within
     * the cybrary.
     *
     * If a discussion is specified, then report whether the user is
     * subscribed to posts to this particular discussion, taking into
     * account the cybrary preference.
     *
     * If it is not specified then only the cybrary preference is considered.
     *
     * @param int $userid The user ID
     * @param \stdClass $cybrary The record of the cybrary to test
     * @param int $discussionid The ID of the discussion to check
     * @param $cm The coursemodule record. If not supplied, this will be calculated using get_fast_modinfo instead.
     * @return boolean
     */
    public static function is_subscribed($userid, $cybrary, $discussionid = null, $cm = null) {
        // If cybrary is force subscribed and has allowforcesubscribe, then user is subscribed.
        if (self::is_forcesubscribed($cybrary)) {
            if (!$cm) {
                $cm = get_fast_modinfo($cybrary->course)->instances['cybrary'][$cybrary->id];
            }
            if (has_capability('mod/cybrary:allowforcesubscribe', \context_module::instance($cm->id), $userid)) {
                return true;
            }
        }

        if ($discussionid === null) {
            return self::is_subscribed_to_cybrary($userid, $cybrary);
        }

        $subscriptions = self::fetch_discussion_subscription($cybrary->id, $userid);

        // Check whether there is a record for this discussion subscription.
        if (isset($subscriptions[$discussionid])) {
            return ($subscriptions[$discussionid] != self::CYBRARY_DISCUSSION_UNSUBSCRIBED);
        }

        return self::is_subscribed_to_cybrary($userid, $cybrary);
    }

    /**
     * Whether a user is subscribed to this cybrary.
     *
     * @param int $userid The user ID
     * @param \stdClass $cybrary The record of the cybrary to test
     * @return boolean
     */
    protected static function is_subscribed_to_cybrary($userid, $cybrary) {
        return self::fetch_subscription_cache($cybrary->id, $userid);
    }

    /**
     * Helper to determine whether a cybrary has it's subscription mode set
     * to forced subscription.
     *
     * @param \stdClass $cybrary The record of the cybrary to test
     * @return bool
     */
    public static function is_forcesubscribed($cybrary) {
        return ($cybrary->forcesubscribe == CYBRARY_FORCESUBSCRIBE);
    }

    /**
     * Helper to determine whether a cybrary has it's subscription mode set to disabled.
     *
     * @param \stdClass $cybrary The record of the cybrary to test
     * @return bool
     */
    public static function subscription_disabled($cybrary) {
        return ($cybrary->forcesubscribe == CYBRARY_DISALLOWSUBSCRIBE);
    }

    /**
     * Helper to determine whether the specified cybrary can be subscribed to.
     *
     * @param \stdClass $cybrary The record of the cybrary to test
     * @return bool
     */
    public static function is_subscribable($cybrary) {
        return (!\mod_cybrary\subscriptions::is_forcesubscribed($cybrary) &&
                !\mod_cybrary\subscriptions::subscription_disabled($cybrary));
    }

    /**
     * Set the cybrary subscription mode.
     *
     * By default when called without options, this is set to CYBRARY_FORCESUBSCRIBE.
     *
     * @param \stdClass $cybrary The record of the cybrary to set
     * @param int $status The new subscription state
     * @return bool
     */
    public static function set_subscription_mode($cybraryid, $status = 1) {
        global $DB;
        return $DB->set_field("cybrary", "forcesubscribe", $status, array("id" => $cybraryid));
    }

    /**
     * Returns the current subscription mode for the cybrary.
     *
     * @param \stdClass $cybrary The record of the cybrary to set
     * @return int The cybrary subscription mode
     */
    public static function get_subscription_mode($cybrary) {
        return $cybrary->forcesubscribe;
    }

    /**
     * Returns an array of cybraries that the current user is subscribed to and is allowed to unsubscribe from
     *
     * @return array An array of unsubscribable cybraries
     */
    public static function get_unsubscribable_cybraries() {
        global $USER, $DB;

        // Get courses that $USER is enrolled in and can see.
        $courses = enrol_get_my_courses();
        if (empty($courses)) {
            return array();
        }

        $courseids = array();
        foreach($courses as $course) {
            $courseids[] = $course->id;
        }
        list($coursesql, $courseparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');

        // Get all cybraries from the user's courses that they are subscribed to and which are not set to forced.
        // It is possible for users to be subscribed to a cybrary in subscription disallowed mode so they must be listed
        // here so that that can be unsubscribed from.
        $sql = "SELECT f.id, cm.id as cm, cm.visible, f.course
                FROM {cybrary} f
                JOIN {course_modules} cm ON cm.instance = f.id
                JOIN {modules} m ON m.name = :modulename AND m.id = cm.module
                LEFT JOIN {cybrary_subscriptions} fs ON (fs.cybrary = f.id AND fs.userid = :userid)
                WHERE f.forcesubscribe <> :forcesubscribe
                AND fs.id IS NOT NULL
                AND cm.course
                $coursesql";
        $params = array_merge($courseparams, array(
            'modulename'=>'cybrary',
            'userid' => $USER->id,
            'forcesubscribe' => CYBRARY_FORCESUBSCRIBE,
        ));
        $cybraries = $DB->get_recordset_sql($sql, $params);

        $unsubscribablecybraries = array();
        foreach($cybraries as $cybrary) {
            if (empty($cybrary->visible)) {
                // The cybrary is hidden - check if the user can view the cybrary.
                $context = \context_module::instance($cybrary->cm);
                if (!has_capability('moodle/course:viewhiddenactivities', $context)) {
                    // The user can't see the hidden cybrary to cannot unsubscribe.
                    continue;
                }
            }

            $unsubscribablecybraries[] = $cybrary;
        }
        $cybraries->close();

        return $unsubscribablecybraries;
    }

    /**
     * Get the list of potential subscribers to a cybrary.
     *
     * @param context_module $context the cybrary context.
     * @param integer $groupid the id of a group, or 0 for all groups.
     * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
     * @param string $sort sort order. As for get_users_by_capability.
     * @return array list of users.
     */
    public static function get_potential_subscribers($context, $groupid, $fields, $sort = '') {
        global $DB;

        // Only active enrolled users or everybody on the frontpage.
        list($esql, $params) = get_enrolled_sql($context, 'mod/cybrary:allowforcesubscribe', $groupid, true);
        if (!$sort) {
            list($sort, $sortparams) = users_order_by_sql('u');
            $params = array_merge($params, $sortparams);
        }

        $sql = "SELECT $fields
                FROM {user} u
                JOIN ($esql) je ON je.id = u.id
            ORDER BY $sort";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Fetch the cybrary subscription data for the specified userid and cybrary.
     *
     * @param int $cybraryid The cybrary to retrieve a cache for
     * @param int $userid The user ID
     * @return boolean
     */
    public static function fetch_subscription_cache($cybraryid, $userid) {
        if (isset(self::$cybrarycache[$userid]) && isset(self::$cybrarycache[$userid][$cybraryid])) {
            return self::$cybrarycache[$userid][$cybraryid];
        }
        self::fill_subscription_cache($cybraryid, $userid);

        if (!isset(self::$cybrarycache[$userid]) || !isset(self::$cybrarycache[$userid][$cybraryid])) {
            return false;
        }

        return self::$cybrarycache[$userid][$cybraryid];
    }

    /**
     * Fill the cybrary subscription data for the specified userid and cybrary.
     *
     * If the userid is not specified, then all subscription data for that cybrary is fetched in a single query and used
     * for subsequent lookups without requiring further database queries.
     *
     * @param int $cybraryid The cybrary to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache($cybraryid, $userid = null) {
        global $DB;

        if (!isset(self::$fetchedcybraries[$cybraryid])) {
            // This cybrary has not been fetched as a whole.
            if (isset($userid)) {
                if (!isset(self::$cybrarycache[$userid])) {
                    self::$cybrarycache[$userid] = array();
                }

                if (!isset(self::$cybrarycache[$userid][$cybraryid])) {
                    if ($DB->record_exists('cybrary_subscriptions', array(
                        'userid' => $userid,
                        'cybrary' => $cybraryid,
                    ))) {
                        self::$cybrarycache[$userid][$cybraryid] = true;
                    } else {
                        self::$cybrarycache[$userid][$cybraryid] = false;
                    }
                }
            } else {
                $subscriptions = $DB->get_recordset('cybrary_subscriptions', array(
                    'cybrary' => $cybraryid,
                ), '', 'id, userid');
                foreach ($subscriptions as $id => $data) {
                    if (!isset(self::$cybrarycache[$data->userid])) {
                        self::$cybrarycache[$data->userid] = array();
                    }
                    self::$cybrarycache[$data->userid][$cybraryid] = true;
                }
                self::$fetchedcybraries[$cybraryid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Fill the cybrary subscription data for all cybraries that the specified userid can subscribe to in the specified course.
     *
     * @param int $courseid The course to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache_for_course($courseid, $userid) {
        global $DB;

        if (!isset(self::$cybrarycache[$userid])) {
            self::$cybrarycache[$userid] = array();
        }

        $sql = "SELECT
                    f.id AS cybraryid,
                    s.id AS subscriptionid
                FROM {cybrary} f
                LEFT JOIN {cybrary_subscriptions} s ON (s.cybrary = f.id AND s.userid = :userid)
                WHERE f.course = :course
                AND f.forcesubscribe <> :subscriptionforced";

        $subscriptions = $DB->get_recordset_sql($sql, array(
            'course' => $courseid,
            'userid' => $userid,
            'subscriptionforced' => CYBRARY_FORCESUBSCRIBE,
        ));

        foreach ($subscriptions as $id => $data) {
            self::$cybrarycache[$userid][$id] = !empty($data->subscriptionid);
        }
        $subscriptions->close();
    }

    /**
     * Returns a list of user objects who are subscribed to this cybrary.
     *
     * @param stdClass $cybrary The cybrary record.
     * @param int $groupid The group id if restricting subscriptions to a group of users, or 0 for all.
     * @param context_module $context the cybrary context, to save re-fetching it where possible.
     * @param string $fields requested user fields (with "u." table prefix).
     * @param boolean $includediscussionsubscriptions Whether to take discussion subscriptions and unsubscriptions into consideration.
     * @return array list of users.
     */
    public static function fetch_subscribed_users($cybrary, $groupid = 0, $context = null, $fields = null,
            $includediscussionsubscriptions = false) {
        global $CFG, $DB;

        if (empty($fields)) {
            $allnames = get_all_user_name_fields(true, 'u');
            $fields ="u.id,
                      u.username,
                      $allnames,
                      u.maildisplay,
                      u.mailformat,
                      u.maildigest,
                      u.imagealt,
                      u.email,
                      u.emailstop,
                      u.city,
                      u.country,
                      u.lastaccess,
                      u.lastlogin,
                      u.picture,
                      u.timezone,
                      u.theme,
                      u.lang,
                      u.trackcybraries,
                      u.mnethostid";
        }

        // Retrieve the cybrary context if it wasn't specified.
        $context = cybrary_get_context($cybrary->id, $context);

        if (self::is_forcesubscribed($cybrary)) {
            $results = \mod_cybrary\subscriptions::get_potential_subscribers($context, $groupid, $fields, "u.email ASC");

        } else {
            // Only active enrolled users or everybody on the frontpage.
            list($esql, $params) = get_enrolled_sql($context, '', $groupid, true);
            $params['cybraryid'] = $cybrary->id;

            if ($includediscussionsubscriptions) {
                $params['scybraryid'] = $cybrary->id;
                $params['dscybraryid'] = $cybrary->id;
                $params['unsubscribed'] = self::CYBRARY_DISCUSSION_UNSUBSCRIBED;

                $sql = "SELECT $fields
                        FROM (
                            SELECT userid FROM {cybrary_subscriptions} s
                            WHERE
                                s.cybrary = :scybraryid
                                UNION
                            SELECT userid FROM {cybrary_discussion_subs} ds
                            WHERE
                                ds.cybrary = :dscybraryid AND ds.preference <> :unsubscribed
                        ) subscriptions
                        JOIN {user} u ON u.id = subscriptions.userid
                        JOIN ($esql) je ON je.id = u.id
                        ORDER BY u.email ASC";

            } else {
                $sql = "SELECT $fields
                        FROM {user} u
                        JOIN ($esql) je ON je.id = u.id
                        JOIN {cybrary_subscriptions} s ON s.userid = u.id
                        WHERE
                          s.cybrary = :cybraryid
                        ORDER BY u.email ASC";
            }
            $results = $DB->get_records_sql($sql, $params);
        }

        // Guest user should never be subscribed to a cybrary.
        unset($results[$CFG->siteguest]);

        // Apply the activity module availability resetrictions.
        $cm = get_coursemodule_from_instance('cybrary', $cybrary->id, $cybrary->course);
        $modinfo = get_fast_modinfo($cybrary->course);
        $info = new \core_availability\info_module($modinfo->get_cm($cm->id));
        $results = $info->filter_user_list($results);

        return $results;
    }

    /**
     * Retrieve the discussion subscription data for the specified userid and cybrary.
     *
     * This is returned as an array of discussions for that cybrary which contain the preference in a stdClass.
     *
     * @param int $cybraryid The cybrary to retrieve a cache for
     * @param int $userid The user ID
     * @return array of stdClass objects with one per discussion in the cybrary.
     */
    public static function fetch_discussion_subscription($cybraryid, $userid = null) {
        self::fill_discussion_subscription_cache($cybraryid, $userid);

        if (!isset(self::$cybrarydiscussioncache[$userid]) || !isset(self::$cybrarydiscussioncache[$userid][$cybraryid])) {
            return array();
        }

        return self::$cybrarydiscussioncache[$userid][$cybraryid];
    }

    /**
     * Fill the discussion subscription data for the specified userid and cybrary.
     *
     * If the userid is not specified, then all discussion subscription data for that cybrary is fetched in a single query
     * and used for subsequent lookups without requiring further database queries.
     *
     * @param int $cybraryid The cybrary to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_discussion_subscription_cache($cybraryid, $userid = null) {
        global $DB;

        if (!isset(self::$discussionfetchedcybraries[$cybraryid])) {
            // This cybrary hasn't been fetched as a whole yet.
            if (isset($userid)) {
                if (!isset(self::$cybrarydiscussioncache[$userid])) {
                    self::$cybrarydiscussioncache[$userid] = array();
                }

                if (!isset(self::$cybrarydiscussioncache[$userid][$cybraryid])) {
                    $subscriptions = $DB->get_recordset('cybrary_discussion_subs', array(
                        'userid' => $userid,
                        'cybrary' => $cybraryid,
                    ), null, 'id, discussion, preference');
                    foreach ($subscriptions as $id => $data) {
                        self::add_to_discussion_cache($cybraryid, $userid, $data->discussion, $data->preference);
                    }
                    $subscriptions->close();
                }
            } else {
                $subscriptions = $DB->get_recordset('cybrary_discussion_subs', array(
                    'cybrary' => $cybraryid,
                ), null, 'id, userid, discussion, preference');
                foreach ($subscriptions as $id => $data) {
                    self::add_to_discussion_cache($cybraryid, $data->userid, $data->discussion, $data->preference);
                }
                self::$discussionfetchedcybraries[$cybraryid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Add the specified discussion and user preference to the discussion
     * subscription cache.
     *
     * @param int $cybraryid The ID of the cybrary that this preference belongs to
     * @param int $userid The ID of the user that this preference belongs to
     * @param int $discussion The ID of the discussion that this preference relates to
     * @param int $preference The preference to store
     */
    protected static function add_to_discussion_cache($cybraryid, $userid, $discussion, $preference) {
        if (!isset(self::$cybrarydiscussioncache[$userid])) {
            self::$cybrarydiscussioncache[$userid] = array();
        }

        if (!isset(self::$cybrarydiscussioncache[$userid][$cybraryid])) {
            self::$cybrarydiscussioncache[$userid][$cybraryid] = array();
        }

        self::$cybrarydiscussioncache[$userid][$cybraryid][$discussion] = $preference;
    }

    /**
     * Reset the discussion cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking cybrary discussion subscription states.
     */
    public static function reset_discussion_cache() {
        self::$cybrarydiscussioncache = array();
        self::$discussionfetchedcybraries = array();
    }

    /**
     * Reset the cybrary cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking cybrary subscription states.
     */
    public static function reset_cybrary_cache() {
        self::$cybrarycache = array();
        self::$fetchedcybraries = array();
    }

    /**
     * Adds user to the subscriber list.
     *
     * @param int $userid The ID of the user to subscribe
     * @param \stdClass $cybrary The cybrary record for this cybrary.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *      module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return bool|int Returns true if the user is already subscribed, or the cybrary_subscriptions ID if the user was
     *     successfully subscribed.
     */
    public static function subscribe_user($userid, $cybrary, $context = null, $userrequest = false) {
        global $DB;

        if (self::is_subscribed($userid, $cybrary)) {
            return true;
        }

        $sub = new \stdClass();
        $sub->userid  = $userid;
        $sub->cybrary = $cybrary->id;

        $result = $DB->insert_record("cybrary_subscriptions", $sub);

        if ($userrequest) {
            $discussionsubscriptions = $DB->get_recordset('cybrary_discussion_subs', array('userid' => $userid, 'cybrary' => $cybrary->id));
            $DB->delete_records_select('cybrary_discussion_subs',
                    'userid = :userid AND cybrary = :cybraryid AND preference <> :preference', array(
                        'userid' => $userid,
                        'cybraryid' => $cybrary->id,
                        'preference' => self::CYBRARY_DISCUSSION_UNSUBSCRIBED,
                    ));

            // Reset the subscription caches for this cybrary.
            // We know that the there were previously entries and there aren't any more.
            if (isset(self::$cybrarydiscussioncache[$userid]) && isset(self::$cybrarydiscussioncache[$userid][$cybrary->id])) {
                foreach (self::$cybrarydiscussioncache[$userid][$cybrary->id] as $discussionid => $preference) {
                    if ($preference != self::CYBRARY_DISCUSSION_UNSUBSCRIBED) {
                        unset(self::$cybrarydiscussioncache[$userid][$cybrary->id][$discussionid]);
                    }
                }
            }
        }

        // Reset the cache for this cybrary.
        self::$cybrarycache[$userid][$cybrary->id] = true;

        $context = cybrary_get_context($cybrary->id, $context);
        $params = array(
            'context' => $context,
            'objectid' => $result,
            'relateduserid' => $userid,
            'other' => array('cybraryid' => $cybrary->id),

        );
        $event  = event\subscription_created::create($params);
        if ($userrequest && $discussionsubscriptions) {
            foreach ($discussionsubscriptions as $subscription) {
                $event->add_record_snapshot('cybrary_discussion_subs', $subscription);
            }
            $discussionsubscriptions->close();
        }
        $event->trigger();

        return $result;
    }

    /**
     * Removes user from the subscriber list
     *
     * @param int $userid The ID of the user to unsubscribe
     * @param \stdClass $cybrary The cybrary record for this cybrary.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return boolean Always returns true.
     */
    public static function unsubscribe_user($userid, $cybrary, $context = null, $userrequest = false) {
        global $DB;

        $sqlparams = array(
            'userid' => $userid,
            'cybrary' => $cybrary->id,
        );
        $DB->delete_records('cybrary_digests', $sqlparams);

        if ($cybrariesubscription = $DB->get_record('cybrary_subscriptions', $sqlparams)) {
            $DB->delete_records('cybrary_subscriptions', array('id' => $cybrariesubscription->id));

            if ($userrequest) {
                $discussionsubscriptions = $DB->get_recordset('cybrary_discussion_subs', $sqlparams);
                $DB->delete_records('cybrary_discussion_subs',
                        array('userid' => $userid, 'cybrary' => $cybrary->id, 'preference' => self::CYBRARY_DISCUSSION_UNSUBSCRIBED));

                // We know that the there were previously entries and there aren't any more.
                if (isset(self::$cybrarydiscussioncache[$userid]) && isset(self::$cybrarydiscussioncache[$userid][$cybrary->id])) {
                    self::$cybrarydiscussioncache[$userid][$cybrary->id] = array();
                }
            }

            // Reset the cache for this cybrary.
            self::$cybrarycache[$userid][$cybrary->id] = false;

            $context = cybrary_get_context($cybrary->id, $context);
            $params = array(
                'context' => $context,
                'objectid' => $cybrariesubscription->id,
                'relateduserid' => $userid,
                'other' => array('cybraryid' => $cybrary->id),

            );
            $event = event\subscription_deleted::create($params);
            $event->add_record_snapshot('cybrary_subscriptions', $cybrariesubscription);
            if ($userrequest && $discussionsubscriptions) {
                foreach ($discussionsubscriptions as $subscription) {
                    $event->add_record_snapshot('cybrary_discussion_subs', $subscription);
                }
                $discussionsubscriptions->close();
            }
            $event->trigger();
        }

        return true;
    }

    /**
     * Subscribes the user to the specified discussion.
     *
     * @param int $userid The userid of the user being subscribed
     * @param \stdClass $discussion The discussion to subscribe to
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @return boolean Whether a change was made
     */
    public static function subscribe_user_to_discussion($userid, $discussion, $context = null) {
        global $DB;

        // First check whether the user is subscribed to the discussion already.
        $subscription = $DB->get_record('cybrary_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference != self::CYBRARY_DISCUSSION_UNSUBSCRIBED) {
                // The user is already subscribed to the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level subscription. Check for a cybrary level subscription.
        if ($DB->record_exists('cybrary_subscriptions', array('userid' => $userid, 'cybrary' => $discussion->cybrary))) {
            if ($subscription && $subscription->preference == self::CYBRARY_DISCUSSION_UNSUBSCRIBED) {
                // The user is subscribed to the cybrary, but unsubscribed from the discussion, delete the discussion preference.
                $DB->delete_records('cybrary_discussion_subs', array('id' => $subscription->id));
                unset(self::$cybrarydiscussioncache[$userid][$discussion->cybrary][$discussion->id]);
            } else {
                // The user is already subscribed to the cybrary. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = time();
                $DB->update_record('cybrary_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->cybrary = $discussion->cybrary;
                $subscription->discussion = $discussion->id;
                $subscription->preference = time();

                $subscription->id = $DB->insert_record('cybrary_discussion_subs', $subscription);
                self::$cybrarydiscussioncache[$userid][$discussion->cybrary][$discussion->id] = $subscription->preference;
            }
        }

        $context = cybrary_get_context($discussion->cybrary, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'cybraryid' => $discussion->cybrary,
                'discussion' => $discussion->id,
            ),

        );
        $event  = event\discussion_subscription_created::create($params);
        $event->trigger();

        return true;
    }
    /**
     * Unsubscribes the user from the specified discussion.
     *
     * @param int $userid The userid of the user being unsubscribed
     * @param \stdClass $discussion The discussion to unsubscribe from
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @return boolean Whether a change was made
     */
    public static function unsubscribe_user_from_discussion($userid, $discussion, $context = null) {
        global $DB;

        // First check whether the user's subscription preference for this discussion.
        $subscription = $DB->get_record('cybrary_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference == self::CYBRARY_DISCUSSION_UNSUBSCRIBED) {
                // The user is already unsubscribed from the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level preference. Check for a cybrary level subscription.
        if (!$DB->record_exists('cybrary_subscriptions', array('userid' => $userid, 'cybrary' => $discussion->cybrary))) {
            if ($subscription && $subscription->preference != self::CYBRARY_DISCUSSION_UNSUBSCRIBED) {
                // The user is not subscribed to the cybrary, but subscribed from the discussion, delete the discussion subscription.
                $DB->delete_records('cybrary_discussion_subs', array('id' => $subscription->id));
                unset(self::$cybrarydiscussioncache[$userid][$discussion->cybrary][$discussion->id]);
            } else {
                // The user is not subscribed from the cybrary. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = self::CYBRARY_DISCUSSION_UNSUBSCRIBED;
                $DB->update_record('cybrary_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->cybrary = $discussion->cybrary;
                $subscription->discussion = $discussion->id;
                $subscription->preference = self::CYBRARY_DISCUSSION_UNSUBSCRIBED;

                $subscription->id = $DB->insert_record('cybrary_discussion_subs', $subscription);
            }
            self::$cybrarydiscussioncache[$userid][$discussion->cybrary][$discussion->id] = $subscription->preference;
        }

        $context = cybrary_get_context($discussion->cybrary, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'cybraryid' => $discussion->cybrary,
                'discussion' => $discussion->id,
            ),

        );
        $event  = event\discussion_subscription_deleted::create($params);
        $event->trigger();

        return true;
    }

}
