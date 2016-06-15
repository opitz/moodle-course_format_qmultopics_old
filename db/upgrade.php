<?php

function xmldb_format_qmultopics_upgrade($oldversion = 0) {

    global $DB;
    $dbman = $DB->get_manager();
    $result = true;

    if ($oldversion < 2012061101) {

        // Define table qmultopics_newssettings to be created.
        $table = new xmldb_table('qmultopics_newssettings');

        // Adding fields to table qmultopics_newssettings.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('displaynews', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1');
        $table->add_field('image', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);

        // Adding keys to table qmultopics_newssettings.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table qmultopics_newssettings.
        $table->add_index('courseid_index', XMLDB_INDEX_NOTUNIQUE, array('courseid'));

        // Conditionally launch create table for qmultopics_newssettings.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Qmultopics savepoint reached.
        upgrade_plugin_savepoint(true, 2012061101, 'format', 'qmultopics');
    }
    if ($oldversion < 2012062506) {
        $table = new xmldb_table('qmultopics_newssettings');
        
        // Define field alttext to be added to qmultopics_newssettings.
        $field = new xmldb_field('alttext', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'image');

        // Conditionally launch add field alttext.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }        
        
        // Define field usestatictext to be added to qmultopics_newssettings.
        $field = new xmldb_field('usestatictext', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'alttext');

        // Conditionally launch add field usestatictext.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field statictext to be added to qmultopics_newssettings.
        $field = new xmldb_field('statictext', XMLDB_TYPE_TEXT, 'big', null, null, null, null, 'usestatictext');

        // Conditionally launch add field statictext.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Define field statictextformat to be added to qmultopics_newssettings.
        $field = new xmldb_field('statictextformat', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'statictext');

        // Conditionally launch add field statictextformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Qmultopics savepoint reached.
        upgrade_plugin_savepoint(true, 2012062506, 'format', 'qmultopics');
    }

    if ($oldversion < 2012062900) {
        $table = new xmldb_table('qmultopics_newssettings');

        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'format_qmultopics_news');
        }

        upgrade_plugin_savepoint(true, 2012062600, 'format', 'qmultopics');
    }

    if ($oldversion < 2012062901) {
        $table = new xmldb_table('format_qmultopics_news');
        $field = new xmldb_field('image');

        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2012062901, 'format', 'qmultopics');
    }

    if ($oldversion < 2016021700) {

        // Define field shownewsfull to be added to format_qmultopics_news.
        $table = new xmldb_table('format_qmultopics_news');
        $field = new xmldb_field('shownewsfull', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'statictextformat');

        // Conditionally launch add field shownewsfull.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Qmultopics savepoint reached.
        upgrade_plugin_savepoint(true, 2016021700, 'format', 'qmultopics');
    }

    return $result;
}