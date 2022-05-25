import Section from 'core_courseformat/local/content/section';
import Header from 'core_courseformat/local/content/section/header';

export default class extends Section {
    // course/format/amd/src/local/content/section.js
    // course/format/amd/src/local/courseeditor/dndsection

    /**
     * Initial state ready method.
     *
     * @param {Object} state the initial state
     */
    stateReady(state) {
        this.configState(state);
        // Drag and drop is only available for components compatible course formats.
        if (this.reactive.isEditing && this.reactive.supportComponents) {
            // Section zero and other formats sections may not have a title to drag.
            const sectionItem = this.getElement(this.selectors.SECTION_ITEM);
            if (sectionItem) {
                // Init the inner dragable element.
                const headerComponent = new Header({
                    ...this,
                    element: sectionItem,
                    fullregion: this.element,
                });
                headerComponent.draggable = false; // <---- my modification - disable drag&drop of sections for now.
                this.configDragDrop(headerComponent);
            }
        }
    }

}