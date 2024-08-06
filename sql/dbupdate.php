<#1>
<?php
global $DIC;
$db = $DIC->database();

if (!$db->tableExists('xaic_config')) {
    $fields = [
        'name' => [
            'type' => 'text',
            'length' => 250,
            'notnull' => true
        ],
        'value' => [
            'type' => 'text',
            'length' => 4000,
            'notnull' => false
        ]
    ];

    $db->createTable('xaic_config', $fields);
    $db->addPrimaryKey('xaic_config', ['name']);
}

if (!$db->tableExists('xaic_objects')) {
    $fields = [
        'id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true
        ],
        'online' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => false
        ],
        'api_key' => [
            'type' => 'text',
            'length' => 250,
            'notnull' => false
        ],
        'disclaimer' => [
            'type' => 'text',
            'length' => 4000,
            'notnull' => false
        ]
    ];

    $db->createTable('xaic_objects', $fields);
    $db->addPrimaryKey('xaic_objects', ['id']);
}

if (!$db->tableExists('xaic_chats')) {
    $fields = [
        'id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true
        ],
        'obj_id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true
        ],
        'created_at' => [
            'type' => 'timestamp',
            'notnull' => true
        ],
        'user_id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true
        ],
    ];

    $db->createTable('xaic_chats', $fields);
    $db->addPrimaryKey('xaic_chats', ['id']);
    $db->addIndex('xaic_chats', ['obj_id'], 'i_1');
    $db->createSequence('xaic_chats');
}

if (!$db->tableExists('xaic_messages')) {
    $fields = [
        'id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true
        ],
        'chat_id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true
        ],
        'date' => [
            'type' => 'timestamp',
            'notnull' => true
        ],
        'role' => [
            'type' => 'text',
            'length' => 250,
            'notnull' => true
        ],
        'message' => [
            'type' => 'text',
            'length' => 4000,
            'notnull' => true
        ]
    ];

    $db->createTable('xaic_messages', $fields);
    $db->addPrimaryKey('xaic_messages', ['id']);
    $db->addIndex('xaic_messages', ['chat_id'], 'i_2');
    $db->createSequence('xaic_messages');
}
?>