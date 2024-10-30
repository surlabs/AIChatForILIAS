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
        'title' => [
            'type' => 'text',
            'length' => 250,
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
        'last_update' => [
            'type' => 'timestamp',
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
<#2>
<?php
global $DIC;
$db = $DIC->database();
if ($db->tableExists('xaic_config')) {

    $result = $db->query("SELECT value FROM xaic_config WHERE name = 'llm_model'");

    while ($row = $db->fetchAssoc($result)) {
        $model = str_replace('openai_', '', $row['value']);

        $db->manipulate("UPDATE xaic_config SET value = '$model' WHERE name = 'llm_model'");
    }
}
?>
<#3>
<?php
global $DIC;
$db = $DIC->database();
if ($db->tableExists('xaic_objects')) {
    $db->addTableColumn('xaic_objects', 'provider', [
        'type' => 'text',
        'length' => 250,
        'notnull' => false
    ]);

    $db->addTableColumn('xaic_objects', 'model', [
        'type' => 'text',
        'length' => 250,
        'notnull' => false
    ]);

    $db->addTableColumn('xaic_objects', 'streaming', [
        'type' => 'integer',
        'length' => 4,
        'notnull' => false
    ]);

    $db->addTableColumn('xaic_objects', 'url', [
        'type' => 'text',
        'length' => 250,
        'notnull' => false
    ]);

    $db->addTableColumn('xaic_objects', 'prompt', [
        'type' => 'text',
        'length' => 4000,
        'notnull' => false
    ]);

    $db->addTableColumn('xaic_objects', 'char_limit', [
        'type' => 'integer',
        'length' => 4,
        'notnull' => false
    ]);

    $db->addTableColumn('xaic_objects', 'max_memory_messages', [
        'type' => 'integer',
        'length' => 4,
        'notnull' => false
    ]);
}
?>
<#4>
<?php
global $DIC;
$db = $DIC->database();
if ($db->tableExists('xaic_config')) {
    $config = [];
    $new_config = [];

    $result = $db->query("SELECT * FROM xaic_config");

    while ($row = $db->fetchAssoc($result)) {
        $config[$row['name']] = $row['value'];
    }

    if (isset($config['llm_provider'])) {
        switch ($config['llm_provider']) {
            case 'openai':
                $new_config['service_to_use'] = 'openai';

                if (isset($config['llm_model'])) {
                    $new_config['openai_model'] = $config['llm_model'];
                }

                if (isset($config['global_api_key'])) {
                    $new_config['openai_api_key'] = $config['global_api_key'];
                }

                if(isset($config['streaming_enabled'])) {
                    $new_config['openai_streaming'] = $config['streaming_enabled'];
                }
                break;
            case 'custom':
                $new_config['service_to_use'] = 'ollama';

                if(isset($config['llm_url'])) {
                    $new_config['ollama_endpoint'] = $config['llm_url'];
                }

                if(isset($config['llm_model'])) {
                    $new_config['ollama_model'] = $config['llm_model'];
                }
                break;
        }
    }

    if (isset($config['prompt_selection'])) {
        $new_config['prompt'] = $config['prompt_selection'];
    }

    if (isset($config['disclaimer_text'])) {
        $new_config['disclaimer'] = $config['disclaimer_text'];
    }

    if (isset($config['n_memory_messages'])) {
        $new_config['max_memory_messages'] = $config['n_memory_messages'];
    }

    if (isset($config['characters_limit'])) {
        $new_config['characters_limit'] = $config['characters_limit'];
    }

    $db->manipulate("DELETE FROM xaic_config");
    foreach ($new_config as $name => $value) {
        $db->insert('xaic_config', [
            'name' => ["text", $name],
            'value' => ["text", $value]
        ]);
    }
}
if ($db->tableExists('xaic_objects')) {
    $objects = [];
    $objects_updated = [];

    $result = $db->query("SELECT * FROM xaic_objects");

    while ($row = $db->fetchAssoc($result)) {
        $objects[] = $row;
    }

    foreach ($objects as $object) {
        $new_object = [];

        $new_object['id'] = ["integer", $object['id']];
        $new_object['online'] = ["integer", $object['online']];
        $new_object['prompt'] = ["text", $object['prompt']];
        $new_object['disclaimer'] = ["text", $object['disclaimer']];
        $new_object['max_memory_messages'] = ["integer", $object['max_memory_messages']];
        $new_object['characters_limit'] = ["integer", $object['char_limit']];

        if ($object['provider'] == 'openai') {
            $new_object['openai_model'] = ["text", $object['model']];
            $new_object['openai_api_key'] = ["text", $object['api_key']];
            $new_object['openai_streaming'] = ["integer", $object['streaming']];
        } else {
            $new_object['ollama_model'] = ["text", $object['model']];
        }

        $objects_updated[] = $new_object;
    }

    $db->createTable('xaic_objects', array(
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
        'prompt' => [
            'type' => 'text',
            'length' => 4000,
            'notnull' => false
        ],
        'disclaimer' => [
            'type' => 'text',
            'length' => 4000,
            'notnull' => false
        ],
        'max_memory_messages' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => false
        ],
        'characters_limit' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => false
        ],
        'openai_model' => [
            'type' => 'text',
            'length' => 250,
            'notnull' => false
        ],
        'openai_api_key' => [
            'type' => 'text',
            'length' => 250,
            'notnull' => false
        ],
        'openai_streaming' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => false
        ],
        'ollama_model' => [
            'type' => 'text',
            'length' => 250,
            'notnull' => false
        ]
    ), true);

    $db->addPrimaryKey('xaic_objects', ['id']);

    foreach ($objects_updated as $object) {
        $db->insert('xaic_objects', $object);
    }
}
?>