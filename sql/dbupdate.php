<#1>
<?php
if (!$ilDB->tableExists('cron_crnhk_files_list')) {
    $fields = array(
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
            'length' => 255,
            'notnull' => false
        ),
        'parent_type' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true
        ),
        'parent_title' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true
        ),
        'path' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true
        )
    );
    $ilDB->createTable("cron_crnhk_files_list", $fields);
}
?>