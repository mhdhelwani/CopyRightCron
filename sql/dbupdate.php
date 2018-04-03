<#1>
<?php
if (!$ilDB->tableExists('cron_crnhk_files_list')) {
    $fields = array(
        'id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => true
        ),
        'obj_id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => true
        ),
        'ref_id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => true
        ),
        'sub_id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => true
        ),
        'owner' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => true
        ),
        'file_title' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true
        ),
        'file_info' => array(
            'type' => 'text',
            'length' => 4000,
            'notnull' => false
        ),
        'parent_type' => array(
            'type' => 'text',
            'length' => 4000,
            'notnull' => true
        ),
        'parent_title' => array(
            'type' => 'text',
            'length' => 2000,
            'notnull' => true
        ),
        'parent_title_parms' => array(
            'type' => 'text',
            'length' => 4000,
            'notnull' => true
        ),
        'path' => array(
            'type' => 'text',
            'length' => 2000,
            'notnull' => true
        ),
        'path_parms' => array(
            'type' => 'text',
            'length' => 4000,
            'notnull' => true
        ),
        'lng_modules' => array(
            'type' => 'text',
            'length' => 1000,
            'notnull' => true
        )
    );
    $ilDB->createTable("cron_crnhk_files_list", $fields);
    $ilDB->addUniqueConstraint("cron_crnhk_files_list", array("id"));
    $ilDB->createSequence("cron_crnhk_files_list");
}
?>