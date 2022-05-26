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

import Mutations from 'core_courseformat/local/courseeditor/mutations';

/**
 * Mutations
 *
 * @module     format_flexsections/local/courseeditor/mutations
 * @copyright  2022 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export default class extends Mutations {
    // Extends: course/format/amd/src/local/courseeditor/mutations.js

    /**
     * Move course modules to specific course location.
     *
     * @param {StateManager} stateManager the current state manager
     * @param {array} sectionIds the list of section ids to move
     */
    async mergeup(stateManager, sectionIds) {
        const course = stateManager.get('course');
        this.sectionLock(stateManager, sectionIds, true);
        const updates = await this._callEditWebservice('mergeup', course.id, sectionIds);
        stateManager.processUpdates(updates);
        this.sectionLock(stateManager, sectionIds, false);
    }

    /**
     * Add a new section to a specific course location.
     *
     * @param {StateManager} stateManager the current state manager
     * @param {number} parentSectionId optional the parent section id
     */
    async addSubSection(stateManager, parentSectionId) {
        const course = stateManager.get('course');
        let updates;
        if (parentSectionId) {
            updates = await this._callEditWebservice('section_add_subsection', course.id, [parentSectionId]);
        } else {
            updates = await this._callEditWebservice('section_add', course.id, [], 0);
        }
        stateManager.processUpdates(updates);
    }

    /**
     * Switch between section being displayed on a separate page vs on the same page
     *
     * @param {StateManager} stateManager the current state manager
     * @param {number} sectionId
     */
    async sectionSwitchCollapsed(stateManager, sectionId) {
        const course = stateManager.get('course');
        const updates = await this._callEditWebservice('section_switch_collapsed', course.id, [sectionId]);
        stateManager.processUpdates(updates);
    }
}