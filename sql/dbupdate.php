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
    'option_one' => array(
        'type' => 'text',
        'length' => 10,
        'fixed' => false,
        'notnull' => false
    ),
    'option_two' => array(
        'type' => 'text',
        'length' => 10,
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
$apikey = array(
    'obj_id' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true
    ),
    'apikey' => array(
        'type' => 'text',
        'length' => 255,
        'fixed' => false,
        'notnull' => false
    )
);

if(!$ilDB->tableExists("rep_robj_xaic_apikey")) {
    $ilDB->createTable("rep_robj_xaic_apikey", $apikey);
    $ilDB->createSequence("rep_robj_xaic_apikey");
    $ilDB->addPrimaryKey("rep_robj_xaic_apikey", array("obj_id"));
}
?>