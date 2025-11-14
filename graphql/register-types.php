<?php

require_once __DIR__ . '/resolvers/forums-resolver.php';
require_once __DIR__ . '/resolvers/topics-resolver.php';

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
                'type' => 'String',
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
            'forumId' => [
                'type' => 'ID',
                'description' => 'The ID of the forum (if applicable).',
            ],
            'subforumId' => [
                'type' => 'ID',
                'description' => 'The ID of the subforum (if applicable).',
            ],
            'type' => [
                'type' => 'String',
                'description' => 'The type of the forum.',
            ],
            'childrenType' => [
                'type' => 'String',
                'description' => 'The type of children forums.',
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
                'type' => 'String',
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
            'forumId' => [
                'type' => 'ID',
                'description' => 'The ID of the forum (if applicable).',
            ],
            'subforumId' => [
                'type' => 'ID',
                'description' => 'The ID of the subforum (if applicable).',
            ],
            'type' => [
                'type' => 'String',
                'description' => 'The type of the forum.',
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
            'authorId' => [
                'type' => 'ID',
                'description' => 'The ID of the author of the reply.',
            ],
            'replyToId' => [
                'type' => 'ID',
                'description' => 'The ID of the reply to which this topic is linked.',
            ],
            'authorIp' => [
                'type' => 'String',
                'description' => 'The IP address of the author of the reply.',
            ],
            'burTotal' => [
                'type' => 'String',
                'description' => 'Total count of posts in the topic including replies.',
            ]
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
            ],
            'rawContent' => [
                'type' => 'String',
                'description' => 'The raw content of the topic.',
            ],
            'author' => [
                'type' => 'String',
                'description' => 'The author of the topic.',
            ],
            'authorRole' => [
                'type' => 'String',
                'description' => 'The role of the author of the topic.',
            ],
            'createdAt' => [
                'type' => 'String',
                'description' => 'The creation date of the topic.',
            ],
            'authorId' => [
                'type' => 'ID',
                'description' => 'The ID of the author of the topic.',
            ],
            'replies' => [
                'type' => ['list_of' => 'Reply'],
                'description' => 'The replies to the topic.',
            ],
            'authorIp' => [
                'type' => 'String',
                'description' => 'The IP address of the author of the reply.',
            ],
            'burTotal' => [
                'type' => 'String',
                'description' => 'Total count of posts in the topic including replies.',
            ],
            'tags' => [
                'type' => ['list_of' => 'TopicTag'],
                'description' => 'The tags of the topic.',
            ]
        ],
    ]);

    register_graphql_object_type('BbPressTopicsResults', [
        'description' => 'A bbPress forum.',
        'fields' => [
            'topics' => [
                'type' => ['list_of' => 'Topics'],
                'description' => 'List of all topics in a bbPress forum by forumId.',
            ],
            'hasMore' => [
                'type' => 'Boolean',
                'description' => 'Indicates if there are more topics to load beyond the current set.',
            ],
        ],
    ]);

    register_graphql_object_type('BbPressSearchResult', [
        'description' => 'A bbPress search result.',
        'fields' => [
            'id' => [
                'type' => 'ID',
                'description' => 'The ID of the post.',
            ],
            'title' => [
                'type' => 'String',
                'description' => 'The title of the post.',
            ],
            'content' => [
                'type' => 'String',
                'description' => 'The content of the post.',
            ],
            'topicCount' => [
                'type' => 'Int',
                'description' => 'The number of topics in the post.',
            ],
            'postCount' => [
                'type' => 'String',
                'description' => 'The total number of posts in the post.',
            ],
            'freshnessLink' => [
                'type' => 'String',
                'description' => 'The freshness link of the post.',
            ],
            'freshnessAuthor' => [
                'type' => 'String',
                'description' => 'The freshness author of the post.',
            ],
            'voicesCount' => [
                'type' => 'Int',
                'description' => 'The number of unique voices in the topic.',
            ],
            'createdAt' => [
                'type' => 'String',
                'description' => 'The creation date of the topic.',
            ],
            'type' => [
                'type' => 'String',
                'description' => 'The type of the post (forum, topic, reply).',
            ],
            'forumId' => [
                'type' => 'ID',
                'description' => 'The ID of the forum (if applicable).',
            ],
            'subforumId' => [
                'type' => 'ID',
                'description' => 'The ID of the subforum (if applicable).',
            ],
        ],
    ]);

    register_graphql_object_type('BbPressSearchResults', [
        'description' => 'A bbPress forum.',
        'fields' => [
            'results' => [
                'type' => ['list_of' => 'BbPressSearchResult'],
                'description' => 'List of all bbPress search results.',
            ],
            'hasMore' => [
                'type' => 'Boolean',
                'description' => 'Indicates if there are more results to load beyond the current set.',
            ],
        ],
    ]);
});
