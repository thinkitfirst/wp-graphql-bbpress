<?php

add_action('graphql_register_types', function () {
    register_graphql_field('RootQuery', 'bbpressForums', [
        'type' => ['list_of' => 'Forum'],
        'description' => 'List of all bbPress forums, or subforums if forumId is provided.',
        'args' => [
            'forumId' => [
                'type' => 'ID',
                'description' => 'Optional ID to fetch subforums.',
                'default' => null,
            ],
        ],
        'resolve' => function($source, $args) {
            if ($args['forumId']) {
                return resolve_forums($args['forumId']);
            }
            return resolve_forums();
        },
    ]);

    register_graphql_field('RootQuery', 'bbpressTopics', [
        'type' => ['list_of' => 'Topics'],
        'description' => 'List of all topics in a bbPress forum by forumId.',
        'args' => [
            'forumId' => [
                'type' => 'ID',
                'description' => 'The ID of the forum for which topics are being fetched.',
                'required' => true,
            ],
        ],
        'resolve' => function($source, $args) {
            return resolve_topics($args['forumId']);
        },
    ]);

    register_graphql_field('RootQuery', 'bbpressForum', [
        'type' => 'Forum',
        'description' => 'A single bbPress forum by ID.',
        'args' => [
            'id' => [
                'type' => 'ID',
                'description' => 'The ID of the forum.',
            ],
        ],
        'resolve' => function($source, $args) {
            return resolve_forum($args['id']);
        },
    ]);

    register_graphql_field('RootQuery', 'bbpressTopic', [
        'type' => 'Topic',
        'description' => 'A single bbPress topic by ID.',
        'args' => [
            'id' => [
                'type' => 'ID',
                'description' => 'The ID of the topic.',
                'required' => true,
            ],
        ],
        'resolve' => function($source, $args) {
            return resolve_topic($args['id']);
        },
    ]);
});