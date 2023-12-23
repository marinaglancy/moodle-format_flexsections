/* eslint-disable camelcase */
// Javascript functions for Flexible sections course format.

// This is no longer used but there are errors in console if this file is missing.

M.course = M.course || {};

M.course.format = M.course.format || {};

/**
 * Get sections config for this format.
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
        container_node: 'ul',
        container_class: 'flexsections',
        section_node: 'li',
        section_class: 'section'
    };
};
