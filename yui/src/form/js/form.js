/**
 * JavaScript for form editing attempts conditions.
 *
 * @module moodle-availability_attempts-form
 */
M.availability_attempts = M.availability_attempts || {};

/**
 * @class M.availability_attempts.form
 * @extends M.core_availability.plugin
 */
M.availability_attempts.form = Y.Object(M.core_availability.plugin);

/**
 * Initialises this plugin.
 *
 * @method initInner
 * @param {Array} cms Array of objects containing cmid => name
 */
M.availability_attempts.form.initInner = function(cms) {
    this.cms = cms;
};

M.availability_attempts.form.getNode = function(json) {
    // Create HTML structure.
    var html = '<span class="col-form-label p-r-1"> ' + M.util.get_string('title', 'availability_attempts') + '</span>' +
               ' <span class="availability-group form-group"><label>' +
            '<span class="accesshide">' + M.util.get_string('label_cm', 'availability_attempts') + ' </span>' +
            '<select class="custom-select" name="cm" title="' + M.util.get_string('label_cm', 'availability_attempts') + '">' +
            '<option value="0">' + M.util.get_string('choosedots', 'moodle') + '</option>';
    for (var i = 0; i < this.cms.length; i++) {
        var cm = this.cms[i];
        // String has already been escaped using format_string.
        html += '<option value="' + cm.id + '">' + cm.name + '</option>';
    }
    html += '</select></label></span>';
    var node = Y.Node.create('<span class="form-inline">' + html + '</span>');

    // Set initial values.
    if (json.cm !== undefined &&
            node.one('select[name=cm] > option[value=' + json.cm + ']')) {
        node.one('select[name=cm]').set('value', '' + json.cm);
    }

    // Add event handlers (first time only).
    if (!M.availability_attempts.form.addedEvents) {
        M.availability_attempts.form.addedEvents = true;
        var root = Y.one('.availability-field');
        root.delegate('change', function() {
            // Whichever dropdown changed, just update the form.
            M.core_availability.form.update();
        }, '.availability_attempts select');
    }

    return node;
};

M.availability_attempts.form.fillValue = function(value, node) {
    value.cm = parseInt(node.one('select[name=cm]').get('value'), 10);
};

M.availability_attempts.form.fillErrors = function(errors, node) {
    var cmid = parseInt(node.one('select[name=cm]').get('value'), 10);
    if (cmid === 0) {
        errors.push('availability_attempts:error_selectcmid');
    }
};
