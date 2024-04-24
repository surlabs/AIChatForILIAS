<#1>
<?php
GLOBAL $DIC;
$ilDB = $DIC->database();
$fields = array(
    'id' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true
    ),
    'is_online' => array(
        'type' => 'integer',
        'length' => 1,
        'notnull' => false
    ),
    'apikey' => array(
        'type' => 'text',
        'length' => 255,
        'fixed' => false,
        'notnull' => false
    )
);



if(!$ilDB->tableExists("rep_robj_xaic_data")) {
    $ilDB->createTable("rep_robj_xaic_data", $fields);
    $ilDB->addPrimaryKey("rep_robj_xaic_data", array("id"));
}
?>

<#2>
<?php
$config = array(
    'key_setting' => array(
        'type' => 'text',
        'length' => 50,
        'notnull' => true,
    ),
    'value_setting' => array(
        'type' => 'text',
        'length' => 255,
        'fixed' => false,
        'notnull' => false
    ),
);

if(!$ilDB->tableExists("rep_robj_xaic_config")) {
    $ilDB->createTable("rep_robj_xaic_config", $config);
    $ilDB->addPrimaryKey("rep_robj_xaic_config", array("key_setting"));
}
?>

<#3>
<?php
$chats = array(
    'id' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true,
    ),
    'user_id' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true
    ),
    'obj_id' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true
    ),
    'messages' => array(
        'type' => 'text',
        'fixed' => false,
        'notnull' => false
    ),
    'date' => array(
        'type' => 'timestamp',
        'notnull' => false
    )
);

if(!$ilDB->tableExists("rep_robj_xaic_chats")) {
    $ilDB->createTable("rep_robj_xaic_chats", $chats);
    $ilDB->createSequence("rep_robj_xaic_chats");
    $ilDB->addPrimaryKey("rep_robj_xaic_chats", array("id"));
}
?>

<#4>
<?php
if(!$ilDB->tableColumnExists("rep_robj_xaic_data", "disclaimer")) {
    $ilDB->addTableColumn("rep_robj_xaic_data", "disclaimer", array(
        'type' => 'text',
        'fixed' => false,
        'notnull' => false
    ));
}

?>

<#5>
<?php
if($ilDB->tableColumnExists("rep_robj_xaic_config", "value_setting")) {
    $ilDB->modifyTableColumn("rep_robj_xaic_config", "value_setting", array(
        'type' => 'text',
        'length' => 4000,
        'fixed' => false,
        'notnull' => false
    ));
}
?>