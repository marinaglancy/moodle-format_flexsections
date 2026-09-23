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
     * Merge section with its parent.
     *
     * @param {StateManager} stateManager the current state manager
     * @param {number} sectionId
     */
    async sectionMergeUp(stateManager, sectionId) {
        this.sectionLock(stateManager, [sectionId], true);
        const course = stateManager.get('course');
        const updates = await this._callEditWebservice('section_mergeup', course.id, [], sectionId);
        stateManager.processUpdates(updates);
        this.sectionLock(stateManager, [sectionId], false);
    }

    /**
     * Add a new subsection to a specific section.
     *
     * @param {StateManager} stateManager the current state manager
     * @param {number} parentSectionId
     */
    async addSubSection(stateManager, parentSectionId) {
        const course = stateManager.get('course');
        const updates = await this._callEditWebservice('section_add_subsection', course.id, [], parentSectionId);
        stateManager.processUpdates(updates);
    }

    /**
     * Add a new section to a specific course location.
     *
     * @param {StateManager} stateManager the current state manager
     * @param {number} parentSectionId optional the target section id
     */
    async insertSubSection(stateManager, parentSectionId) {
        const course = stateManager.get('course');
        const updates = await this._callEditWebservice('section_insert_subsection', course.id, [], parentSectionId);
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

    /**
     * Get log entry for the current action (called by the core mutations in Moodle 5.3 and above).
     *
     * Flexsections moves sections to the beginning of another section by calling "section_move_after"
     * with the negated id of the parent section. This id does not exist in the course state, so the
     * feedback message is built from the parent section instead.
     *
     * @param {StateManager} stateManager the current state manager
     * @param {string} action the action name
     * @param {int[]|null} itemIds the element ids
     * @param {Object|undefined} data extra params for the log entry
     * @return {Object} the log entry
     */
    async _getLoggerEntry(stateManager, action, itemIds, data = {}) {
        if (action === 'section_move_after' && data.targetSectionId < 0) {
            return super._getLoggerEntry(stateManager, 'section_move_into', itemIds, {
                ...data,
                targetSectionId: -data.targetSectionId,
                component: 'format_flexsections',
            });
        }
        return super._getLoggerEntry(stateManager, action, itemIds, data);
    }
}