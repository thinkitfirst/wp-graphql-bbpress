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
        'resolve' => function ($source, $args) {
            if (!empty(bbp_forum_get_subforums($args['forumId'])) && $args['forumId']) {
                return resolve_forums($args['forumId']);
            } else if (bbp_get_forum_topic_count($args['forumId'], true)) {
                return resolve_topics($args);
            } else {
                return resolve_forums();
            }
        },
    ]);

    register_graphql_field('RootQuery', 'bbpressTopics', [
        'type' => 'BbPressTopicsResults',
        'description' => 'List of bbPress topics by forum ID with pagination.',
        'args' => [
            'forumId' => [
                'type' => 'ID',
                'description' => 'The ID of the forum for which topics are being fetched.',
                'required' => true,
            ],
            'offset' => [
                'type' => 'Int',
                'description' => 'Offset for pagination.',
                'defaultValue' => 0,
            ],
            'limit' => [
                'type' => 'Int',
                'description' => 'Number of topics to return.',
                'defaultValue' => 15,
            ],
        ],
        'resolve' => function ($source, $args) {
            return resolve_topics($args);
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
        'resolve' => function ($source, $args) {
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
        'resolve' => function ($source, $args) {
            return resolve_topic($args['id']);
        },
    ]);

    register_graphql_field('RootQuery', 'bbpressSearch', [
        'type' => 'BbPressSearchResults',
        'description' => 'Search bbPress forums, topics, and replies by a search term.',
        'args' => [
            'query' => [
                'type' => 'String',
                'description' => 'The search term to query bbPress content.',
                'required' => true,
            ],
            'offset' => [
                'type' => 'Int',
                'description' => 'Offset for pagination.',
                'defaultValue' => 0,
            ],
            'limit' => [
                'type' => 'Int',
                'description' => 'Number of results to return.',
                'defaultValue' => 15,
            ],
        ],
        'resolve' => function ($source, $args) {
            // $default_post_types = bbp_get_post_types(); // We may handle replies later

            $query_args = [
                'post_type'                 => ['forum', 'topic'],
                'posts_per_page'            => $args['limit'],
                's'                         => $args['query'],
                'orderby'                   => 'date',
                'order'                     => 'DESC',
                'ignore_sticky_posts'       => true,
                'perm'                      => 'readable',
                'update_post_family_cache'  => true,
                'offset'                    => $args['offset']
            ];

            $bbp = bbpress();
            $bbp->search_query = new WP_Query($query_args);

            $results = [];

            if ($bbp->search_query->have_posts()) {
                while ($bbp->search_query->have_posts()) {
                    $bbp->search_query->the_post();

                    $id = get_the_ID();
                    $subforumId = bbp_get_topic_forum_id($id);

                    $results[] = [
                        'id'                => bbp_get_forum_id($id),
                        'title'             => bbp_get_forum_title($id),
                        'content'           => bbp_get_forum_content($id),
                        'type'              => get_post_type(),
                        'topicCount'        => bbp_get_forum_topic_count($id),
                        'postCount'         => bbp_get_forum_post_count($id),
                        'freshnessLink'     => bbp_get_forum_freshness_link($id),
                        'freshnessAuthor'   => bbp_get_author_link([
                            'post_id'       => bbp_get_forum_last_active_id($id),
                            'size'          => 14,
                        ]),
                        'voicesCount'       => bbp_get_topic_voice_count($id, true),
                        'createdAt'         => get_the_date(),
                        'forumId'           => bbp_get_forum_parent_id($subforumId),
                        'subforumId'        => $subforumId,
                    ];
                }
                wp_reset_postdata();
            }

            return [
                'results' => $results,
                'hasMore' => $bbp->search_query->found_posts > ($args['offset'] + count($results)),
            ];
        },
    ]);

    // Mutations
    register_graphql_mutation('createBbpressTopic', [
        'inputFields' => [
            'forumId' => [
                'type' => 'ID',
                'description' => 'The ID of the forum to post the topic in.',
                'required' => true,
            ],
            'title' => [
                'type' => 'String',
                'description' => 'The title of the topic.',
                'required' => true,
            ],
            'content' => [
                'type' => 'String',
                'description' => 'The content of the topic.',
                'required' => true,
            ],
        ],
        'outputFields' => [
            'topicId' => [
                'type' => 'ID',
                'description' => 'The ID of the created topic.',
            ],
            'status' => [
                'type' => 'String',
                'description' => 'Result status.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            if (!bbp_current_user_can_access_create_topic_form()) {
                return [
                    'topicId' => null,
                    'status' => 'error: You do not have permission to create a topic.',
                ];
            }

            $forum_id = absint($input['forumId']);
            $title = sanitize_text_field($input['title']);
            $content = sanitize_text_field($input['content']);
            $user_id = get_current_user_id();

            if (empty($forum_id)) {
                return [
                    'topicId' => null,
                    'status' => 'error: forumId is required.',
                ];
            }

            if (empty($title)) {
                return [
                    'topicId' => null,
                    'status' => 'error: title is required.',
                ];
            }

            if (empty($content)) {
                return [
                    'topicId' => null,
                    'status' => 'error: content is required.',
                ];
            }

            $topic_data = [
                'post_title'    => $title,
                'post_content'  => $content,
                'post_status'   => bbp_get_public_status_id(),
                'post_author'   => $user_id,
                'post_parent'   => $forum_id,
            ];

            $topic_id = bbp_insert_topic($topic_data, $forum_id);

            if (is_wp_error($topic_id)) {
                return [
                    'topicId' => null,
                    'status' => 'error: ' . $topic_id->get_error_message(),
                ];
            }

            return [
                'topicId' => $topic_id,
                'status' => 'success',
            ];
        },
    ]);

    register_graphql_mutation('createBbpressReply', [
        'inputFields' => [
            'topicId' => [
                'type' => ['non_null' => 'ID'],
                'description' => 'The ID of the topic to reply to.',
            ],
            'content' => [
                'type' => ['non_null' => 'String'],
                'description' => 'The content of the reply.',
            ],
        ],
        'outputFields' => [
            'replyId' => [
                'type' => 'ID',
                'description' => 'The ID of the created reply.',
            ],
            'status' => [
                'type' => 'String',
                'description' => 'Result status.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            $topic_id = absint($input['topicId']);
            $content = apply_filters('bbp_new_reply_pre_content', $input['content']);

            if (empty($content)) {
                return [
                    'replyId' => null,
                    'status' => 'Content cannot be empty.',
                ];
            }

            $reply_data = [
                'post_parent'   => $topic_id,
                'post_content'  => $content,
                'post_status'   => bbp_get_public_status_id(),
                'post_author'   => get_current_user_id(),
            ];

            $reply_id = bbp_insert_reply($reply_data, $topic_id);

            if (is_wp_error($reply_id)) {
                return [
                    'replyId' => null,
                    'status' => 'error: ' . $reply_id->get_error_message(),
                ];
            }

            return [
                'replyId' => $reply_id,
                'status' => 'success',
            ];
        },
    ]);

    register_graphql_mutation('createBbpressFavorite', [
        'inputFields' => [
            'topicId' => [
                'type' => 'ID',
                'description' => 'The ID of the topic to favorite.',
                'required' => true,
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'Whether the favorite was successfully added.',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'A status message.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            $topic_id = absint($input['topicId']);
            $user_id = get_current_user_id();

            if (!bbp_is_user_favorite($user_id, $topic_id)) {
                bbp_add_user_favorite($user_id, $topic_id);
                return [
                    'success' => true,
                    'message' => 'Topic added to favorites.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Topic is already a favorite.',
            ];
        },
    ]);

    register_graphql_mutation('deleteBbpressFavorite', [
        'inputFields' => [
            'topicId' => [
                'type' => 'ID',
                'description' => 'The ID of the topic to unfavorite.',
                'required' => true,
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'Whether the favorite was successfully removed.',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'A status message.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            $topic_id = absint($input['topicId']);
            $user_id = get_current_user_id();

            if (bbp_is_user_favorite($user_id, $topic_id)) {
                bbp_remove_user_favorite($user_id, $topic_id);
                return [
                    'success' => true,
                    'message' => 'Topic removed from favorites.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Topic is not a favorite.',
            ];
        },
    ]);

    register_graphql_mutation('createBbpressTopicSubscription', [
        'inputFields' => [
            'topicId' => [
                'type' => 'ID',
                'description' => 'The ID of the topic to subscribe to.',
                'required' => true,
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'Whether the subscription was successfully added.',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'A status message.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            $topic_id = absint($input['topicId']);
            $user_id = get_current_user_id();

            if (!bbp_is_user_subscribed($user_id, $topic_id)) {
                bbp_add_user_subscription($user_id, $topic_id);
                return [
                    'success' => true,
                    'message' => 'User subscribed to topic.',
                ];
            }

            return [
                'success' => false,
                'message' => 'User is already subscribed to this topic.',
            ];
        },
    ]);

    register_graphql_mutation('deleteBbpressTopicSubscription', [
        'inputFields' => [
            'topicId' => [
                'type' => 'ID',
                'description' => 'The ID of the topic to unsubscribe from.',
                'required' => true,
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'Whether the unsubscription was successful.',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'A status message.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            $topic_id = absint($input['topicId']);
            $user_id = get_current_user_id();

            if (bbp_is_user_subscribed($user_id, $topic_id)) {
                bbp_remove_user_subscription($user_id, $topic_id);
                return [
                    'success' => true,
                    'message' => 'User unsubscribed from topic.',
                ];
            }

            return [
                'success' => false,
                'message' => 'User is not subscribed to this topic.',
            ];
        },
    ]);

    register_graphql_mutation('updateBbpressReply', [
        'inputFields' => [
            'replyId' => [
                'type' => ['non_null' => 'ID'],
                'description' => 'The ID of the reply to update.',
                'required' => true,
            ],
            'content' => [
                'type' => ['non_null' => 'String'],
                'description' => 'The new content for the reply.',
                'required' => true,
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'Whether the reply was successfully updated.',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'A status message.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            $reply_id = absint($input['replyId']);
            $content = apply_filters('bbp_edit_reply_pre_content', $input['content'], $reply_id);
            $user_id = get_current_user_id();

            if (empty($content)) {
                return [
                    'success' => false,
                    'message' => 'Content cannot be empty.',
                ];
            }

            $reply = get_post($reply_id);

            if (!$reply || $reply->post_type !== bbp_get_reply_post_type()) {
                return [
                    'success' => false,
                    'message' => 'Reply not found.',
                ];
            }

            if ((int) $reply->post_author !== $user_id && !current_user_can('edit_others_replies')) {
                return [
                    'success' => false,
                    'message' => 'You do not have permission to edit this reply.',
                ];
            }

            $update = wp_update_post([
                'ID'           => $reply_id,
                'post_content' => $content,
            ], true);

            if (is_wp_error($update)) {
                return [
                    'success' => false,
                    'message' => 'Failed to update reply: ' . $update->get_error_message(),
                ];
            }

            return [
                'success' => true,
                'message' => 'Reply updated successfully.',
            ];
        },
    ]);

    register_graphql_mutation('deleteBbpressReply', [
        'inputFields' => [
            'replyId' => [
                'type' => 'ID',
                'description' => 'The ID of the reply to delete.',
                'required' => true,
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'Whether the reply was successfully deleted.',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'A status message.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            $reply_id = absint($input['replyId']);
            $user_id = get_current_user_id();

            $reply = get_post($reply_id);

            if (!$reply || $reply->post_type !== bbp_get_reply_post_type()) {
                return [
                    'success' => false,
                    'message' => 'Reply not found.',
                ];
            }

            if ((int) $reply->post_author !== $user_id && !current_user_can('delete_others_replies')) {
                return [
                    'success' => false,
                    'message' => 'You do not have permission to delete this reply.',
                ];
            }

            $deleted = wp_delete_post($reply_id, true);

            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete reply.',
                ];
            }

            return [
                'success' => true,
                'message' => 'Reply deleted successfully.',
            ];
        },
    ]);

    register_graphql_mutation('createBbpressForumSubscription', [
        'inputFields' => [
            'forumId' => [
                'type' => 'ID',
                'description' => 'The ID of the forum to subscribe to.',
                'required' => true,
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'Whether the subscription was successfully added.',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'A status message.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            $forum_id = absint($input['forumId']);
            $user_id = get_current_user_id();

            if (!bbp_is_user_subscribed($user_id, $forum_id)) {
                bbp_add_user_subscription($user_id, $forum_id);
                return [
                    'success' => true,
                    'message' => 'User subscribed to forum.',
                ];
            }

            return [
                'success' => false,
                'message' => 'User is already subscribed to this forum.',
            ];
        },
    ]);

    register_graphql_mutation('deleteBbpressForumSubscription', [
        'inputFields' => [
            'forumId' => [
                'type' => 'ID',
                'description' => 'The ID of the forum to unsubscribe from.',
                'required' => true,
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'Whether the unsubscription was successful.',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'A status message.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            $forum_id = absint($input['forumId']);
            $user_id = get_current_user_id();

            if (bbp_is_user_subscribed($user_id, $forum_id)) {
                bbp_remove_user_subscription($user_id, $forum_id);
                return [
                    'success' => true,
                    'message' => 'User unsubscribed from forum.',
                ];
            }

            return [
                'success' => false,
                'message' => 'User is not subscribed to this forum.',
            ];
        },
    ]);

    register_graphql_mutation('deleteBbpressTopic', [
        'inputFields' => [
            'topicId' => [
                'type' => 'ID',
                'description' => 'The ID of the topic to delete.',
                'required' => true,
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'Whether the topic was successfully deleted.',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'A status message.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            $topic_id = absint($input['topicId']);
            $user_id = get_current_user_id();

            $topic = get_post($topic_id);

            if (!$topic || $topic->post_type !== bbp_get_topic_post_type()) {
                return [
                    'success' => false,
                    'message' => 'Topic not found.',
                ];
            }

            if ((int) $topic->post_author !== $user_id && !current_user_can('delete_others_topics')) {
                return [
                    'success' => false,
                    'message' => 'You do not have permission to delete this topic.',
                ];
            }

            $deleted = wp_delete_post($topic_id, true);

            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete topic.',
                ];
            }

            return [
                'success' => true,
                'message' => 'Topic deleted successfully.',
            ];
        },
    ]);

    register_graphql_mutation('updateBbpressTopic', [
        'inputFields' => [
            'topicId' => [
                'type' => ['non_null' => 'ID'],
                'description' => 'The ID of the topic to update.',
                'required' => true,
            ],
            'content' => [
                'type' => ['non_null' => 'String'],
                'description' => 'The new content for the topic.',
                'required' => true,
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'Whether the topic was successfully updated.',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'A status message.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            $topic_id = absint($input['topicId']);
            $content = apply_filters('bbp_edit_topic_pre_content', $input['content'], $topic_id);
            $user_id = get_current_user_id();

            if (empty($content)) {
                return [
                    'success' => false,
                    'message' => 'Content cannot be empty.',
                ];
            }

            $topic = get_post($topic_id);

            if (!$topic || $topic->post_type !== bbp_get_topic_post_type()) {
                return [
                    'success' => false,
                    'message' => 'Topic not found.',
                ];
            }

            if ((int) $topic->post_author !== $user_id && !current_user_can('edit_others_topics')) {
                return [
                    'success' => false,
                    'message' => 'You do not have permission to edit this topic.',
                ];
            }

            $update = wp_update_post([
                'ID'           => $topic_id,
                'post_content' => $content,
            ], true);

            if (is_wp_error($update)) {
                return [
                    'success' => false,
                    'message' => 'Failed to update topic: ' . $update->get_error_message(),
                ];
            }

            return [
                'success' => true,
                'message' => 'Topic updated successfully.',
            ];
        },
    ]);
});
