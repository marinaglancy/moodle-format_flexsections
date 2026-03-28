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

namespace format_flexsections\local\helpers;

use cache;
use core_text;

/**
 * Helps to store and retrieve large user preferences
 *
 * @package    format_flexsections
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait preferences {
    /**
     * Name for the part of the preference
     *
     * @param string $name
     * @param int $cnt
     * @return string
     */
    protected static function get_preference_name(string $name, int $cnt = 0): string {
        return $cnt ? "{$name}#{$cnt}" : $name;
    }

    /**
     * Set a preference for the user (value can be longer than 1333)
     *
     * @param string $name
     * @param string|null $value
     * @return void
     */
    public static function set_long_preference(string $name, ?string $value): void {
        $allpreferences = array_filter(get_user_preferences(), function ($prefname) use ($name) {
            return $prefname === $name || (strpos($prefname, "{$name}#") === 0);
        }, ARRAY_FILTER_USE_KEY);
        $len = ceil(core_text::strlen((string)$value) / 1300);
        for ($cnt = 0; $cnt < $len; $cnt++) {
            $pref = self::get_preference_name($name, $cnt);
            set_user_preference($pref, core_text::substr($value, $cnt * 1300, 1300));
            unset($allpreferences[$pref]);
        }
        foreach (array_keys($allpreferences) as $pref) {
            unset_user_preference($pref);
        }
    }

    /**
     * Get a preference for the user (value can be longer than 1333)
     *
     * @param string $name
     * @return string
     */
    public static function get_long_preference(string $name): string {
        $allpreferences = array_filter(get_user_preferences(), function ($prefname) use ($name) {
            return $prefname === $name || (strpos($prefname, "{$name}#") === 0);
        }, ARRAY_FILTER_USE_KEY);
        $value = '';
        for ($cnt = 0; $cnt < count($allpreferences); $cnt++) {
            $value .= $allpreferences[self::get_preference_name($name, $cnt)] ?? '';
        }
        return $value;
    }

    /**
     * Return the format section preferences.
     *
     * @return array of preferences indexed by preference name
     */
    public function get_sections_preferences_by_preference(): array {
        $course = $this->get_course();
        try {
            $sectionpreferences = json_decode(
                self::get_long_preference("coursesectionspreferences_{$course->id}"),
                true,
            ) ?: [];
        } catch (\Throwable $e) {
            $sectionpreferences = [];
        }
        return $sectionpreferences;
    }

    /**
     * Return the format section preferences.
     *
     * @param string $preferencename preference name
     * @param int[] $sectionids affected section ids
     *
     */
    public function set_sections_preference(string $preferencename, array $sectionids) {
        $sectionpreferences = $this->get_sections_preferences_by_preference();
        $sectionpreferences[$preferencename] = $sectionids;
        $this->persist_to_user_preference($sectionpreferences);
    }

    /**
     * Add section preference ids.
     *
     * @param string $preferencename preference name
     * @param array $sectionids affected section ids
     */
    public function add_section_preference_ids(string $preferencename, array $sectionids): void {
        $sectionpreferences = $this->get_sections_preferences_by_preference();
        if (!isset($sectionpreferences[$preferencename])) {
            $sectionpreferences[$preferencename] = [];
        }
        foreach ($sectionids as $sectionid) {
            if (!in_array($sectionid, $sectionpreferences[$preferencename])) {
                $sectionpreferences[$preferencename][] = $sectionid;
            }
        }
        $this->persist_to_user_preference($sectionpreferences);
    }

    /**
     * Remove section preference ids.
     *
     * @param string $preferencename preference name
     * @param array $sectionids affected section ids
     */
    public function remove_section_preference_ids(string $preferencename, array $sectionids): void {
        $sectionpreferences = $this->get_sections_preferences_by_preference();
        if (!isset($sectionpreferences[$preferencename])) {
            $sectionpreferences[$preferencename] = [];
        }
        foreach ($sectionids as $sectionid) {
            if (($key = array_search($sectionid, $sectionpreferences[$preferencename])) !== false) {
                unset($sectionpreferences[$preferencename][$key]);
            }
        }
        $this->persist_to_user_preference($sectionpreferences);
    }

    /**
     * Persist the section preferences to the user preferences.
     *
     * @param array $sectionpreferences the section preferences
     */
    private function persist_to_user_preference(array $sectionpreferences): void {
        $course = $this->get_course();
        self::set_long_preference('coursesectionspreferences_' . $course->id, json_encode($sectionpreferences));
        // Invalidate section preferences cache.
        $coursesectionscache = cache::make('core', 'coursesectionspreferences');
        $coursesectionscache->delete($course->id);
    }
}
