/* eslint-disable no-console */

import Mutations from 'core_courseformat/local/courseeditor/mutations';

export default class extends Mutations {
    // course/format/amd/src/local/courseeditor/mutations.js

    /**
     * Move course modules to specific course location.
     *
     * @param {StateManager} stateManager the current state manager
     * @param {array} sectionIds the list of section ids to move
     */
    async mergeup(stateManager, sectionIds) {
        console.log('__mergeup__ '+sectionIds);
        const course = stateManager.get('course');
        this.sectionLock(stateManager, sectionIds, true);
        const updates = await this._callEditWebservice('mergeup', course.id, sectionIds);
        console.log('updates=');
        console.log(updates);
        stateManager.processUpdates(updates);
        this.sectionLock(stateManager, sectionIds, false);
    }

}