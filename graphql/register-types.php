<?php

require_once __DIR__ . '/resolvers/forums-resolver.php';

add_action('graphql_register_types', function () {
    register_graphql_object_type('Forum', [
        'description' => 'A bbPress forum.',
        'fields' => [
            'id' => [
                'type' => 'ID',
                'description' => 'The ID of the forum.',
            ],
            'title' => [
                'type' => 'String',
                'description' => 'The title of the forum.',
            ],
            'content' => [
                'type' => 'String',
                'description' => 'The content of the forum.',
            ],
            'topicCount' => [
                'type' => 'Int',
                'description' => 'The number of topics in the forum.',
            ],
            'postCount' => [
                'type' => 'Int',
                'description' => 'The total number of posts in the forum.',
            ],
            'freshnessLink' => [
                'type' => 'String',
                'description' => 'The freshness link of the forum.',
            ],
            'freshnessAuthor' => [
                'type' => 'String',
                'description' => 'The freshness author of the forum.',
            ],
        ],
    ]);

    register_graphql_object_type('Topics', [
        'description' => 'bbPress topics.',
        'fields' => [
            'id' => [
                'type' => 'ID',
                'description' => 'The ID of the topic.',
            ],
            'title' => [
                'type' => 'String',
                'description' => 'The title of the topic.',
            ],
            'content' => [
                'type' => 'String',
                'description' => 'The content of the topic.',
            ],
            'postCount' => [
                'type' => 'Int',
                'description' => 'The total number of posts in the topic.',
            ],
            'author' => [
                'type' => 'String',
                'description' => 'The author of the topic.',
            ],
            'createdAt' => [
                'type' => 'String',
                'description' => 'The creation date of the topic.',
            ],
            'voicesCount' => [
                'type' => 'Int',
                'description' => 'The number of unique voices in the topic.',
            ],
            'freshnessLink' => [
                'type' => 'String',
                'description' => 'The freshness link of the topic.',
            ],
            'freshnessAuthor' => [
                'type' => 'String',
                'description' => 'The freshness author of the topic.',
            ],
        ],
    ]);

    register_graphql_object_type('Reply', [
        'description' => 'A bbPress reply.',
        'fields' => [
            'id' => [
                'type' => 'ID',
                'description' => 'The ID of the reply.',
            ],
            'content' => [
                'type' => 'String',
                'description' => 'The content of the reply.',
            ],
            'author' => [
                'type' => 'String',
                'description' => 'The author of the reply.',
            ],
            'createdAt' => [
                'type' => 'String',
                'description' => 'The creation date of the reply.',
            ],
            'authorRole' => [
                'type' => 'String',
                'description' => 'The role of the author of the reply.',
            ],
        ],
    ]);

    register_graphql_object_type('Topic', [
        'description' => 'A bbPress topic.',
        'fields' => [
            'id' => [
                'type' => 'ID',
                'description' => 'The ID of the topic.',
            ],
            'title' => [
                'type' => 'String',
                'description' => 'The title of the topic.',
            ],
            'content' => [
                'type' => 'String',
                'description' => 'The content of the topic.',
                'resolve' => function ($topic) {
                    return bbp_get_topic_content($topic['id']);
                },
            ],
            'replies' => [
                'type' => ['list_of' => 'Reply'],
                'description' => 'The replies to the topic.',
            ],
        ],
    ]);
});