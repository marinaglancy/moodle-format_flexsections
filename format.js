// Javascript functions for Flexsections course format

M.course = M.course || {};

M.course.format = M.course.format || {};

/**
 * Get sections config for this format
 *
 * The section structure is:
 * <ul class="flexsections">
 *  <li class="section">...</li>
 *  <li class="section">...</li>
 *   ...
 * </ul>
 *
 * @return {object} section list configuration
 */
M.course.format.get_config = function() {
    return {
        container_node : 'ul',
        container_class : 'flexsections',
        section_node : 'li',
        section_class : 'section'
    };
}

/**
 * Swap section
 *
 * @param {YUI} Y YUI3 instance
 * @param {string} node1 node to swap to
 * @param {string} node2 node to swap with
 * @return {NodeList} section list
 */
M.course.format.swap_sections = function(Y, node1, node2) {
    var CSS = {
        COURSECONTENT : 'course-content',
        SECTIONADDMENUS : 'section_add_menus'
    };

    var sectionlist = Y.Node.all('.'+CSS.COURSECONTENT+' '+M.course.format.get_section_selector(Y));
    // Swap menus
    sectionlist.item(node1).one('.'+CSS.SECTIONADDMENUS).swap(sectionlist.item(node2).one('.'+CSS.SECTIONADDMENUS));
}

/**
 * Process sections after ajax response
 *
 * @param {YUI} Y YUI3 instance
 * @param {array} response ajax response
 * @param {string} sectionfrom first affected section
 * @param {string} sectionto last affected section
 * @return void
 */
M.course.format.process_sections = function(Y, sectionlist, response, sectionfrom, sectionto) {
    var CSS = {
        SECTIONNAME : 'sectionname'
    };

    if (response.action == 'move') {
        // update sections titles
        for (var i in response.sectiontitles) {
            sectionlist.item(i).one('.'+CSS.SECTIONNAME).setContent(response.sectiontitles[i]);
        }
    }
}

M.course.format.handle_flexsections = function(e) {
    // Prevent the default button action
    e.preventDefault();

    var confirmstring = M.util.get_string('confirmdelete', 'format_flexsections');

    // Create the confirmation dialogue.
    var confirm = new M.core.confirm({
        question: confirmstring,
        modal: true,
        visible: false
    });
    confirm.show();

    // If it is confirmed.
    confirm.on('complete-yes', function() {
        var href = e.currentTarget.getAttribute('href') + '&confirm=1';
        window.location = href;
    });
}

M.course.format.init_flexsections = function(Y) {
    Y.delegate('click', M.course.format.handle_flexsections, 'body', 'li.section > .controls > a.delete');
}